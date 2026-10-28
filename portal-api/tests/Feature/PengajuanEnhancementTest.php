<?php

namespace Tests\Feature;

use App\Models\Layanan;
use App\Models\Pengajuan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PengajuanEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private User $mahasiswa;
    private User $mahasiswa2;
    private User $petugas;
    private Layanan $layanan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->layanan = Layanan::create([
            'nama'           => 'Surat Keterangan Aktif Kuliah',
            'deskripsi'      => 'Layanan penerbitan surat aktif kuliah.',
            'sla_hari_kerja' => 3,
        ]);

        $this->mahasiswa = User::factory()->create([
            'email'    => 'mhs@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'mahasiswa',
        ]);

        $this->mahasiswa2 = User::factory()->create([
            'email'    => 'mhs2@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'mahasiswa',
        ]);

        $this->petugas = User::factory()->create([
            'email'    => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'petugas',
        ]);
    }

    // ─── Phase 1: Keamanan Unggahan & Otorisasi ──────────────────────────────

    public function test_mahasiswa_can_submit_with_valid_document(): void
    {
        $file = UploadedFile::fake()->create('ktm.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->mahasiswa, 'sanctum')
            ->postJson('/api/pengajuan', [
                'layanan_id'     => $this->layanan->id,
                'nama_mahasiswa' => 'Budi Santoso',
                'nim'            => '12345678',
                'email'          => 'budi@test.com',
                'keterangan'     => 'Mohon diproses untuk beasiswa',
                'dokumen'        => $file,
            ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.status', 'Menunggu')
                 ->assertJsonPath('data.nama_mahasiswa', 'Budi Santoso');

        $dokumenPath = $response->json('data.dokumen_path');
        $this->assertNotNull($dokumenPath);
        Storage::assertExists($dokumenPath);
    }

    public function test_upload_rejects_dangerous_file_type(): void
    {
        $file = UploadedFile::fake()->create('script.php', 10, 'application/x-php');

        $response = $this->actingAs($this->mahasiswa, 'sanctum')
            ->postJson('/api/pengajuan', [
                'layanan_id'     => $this->layanan->id,
                'nama_mahasiswa' => 'Attacker',
                'nim'            => '99999999',
                'email'          => 'bad@test.com',
                'dokumen'        => $file,
            ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['dokumen']);
    }

    public function test_document_download_ownership_authorization(): void
    {
        $file = UploadedFile::fake()->create('berkas.pdf', 200, 'application/pdf');

        // Mahasiswa 1 membuat pengajuan
        $submit = $this->actingAs($this->mahasiswa, 'sanctum')
            ->postJson('/api/pengajuan', [
                'layanan_id'     => $this->layanan->id,
                'nama_mahasiswa' => 'Mahasiswa 1',
                'nim'            => '11111111',
                'email'          => 'mhs1@test.com',
                'dokumen'        => $file,
            ]);
        $nomor = $submit->json('data.nomor_pengajuan');

        // Mahasiswa 1 (pemilik) bisa mengunduh
        $ownerResp = $this->actingAs($this->mahasiswa, 'sanctum')
            ->get("/api/pengajuan/{$nomor}/dokumen");
        $ownerResp->assertStatus(200);

        // Mahasiswa 2 (bukan pemilik) DITOLAK (404)
        $otherResp = $this->actingAs($this->mahasiswa2, 'sanctum')
            ->get("/api/pengajuan/{$nomor}/dokumen");
        $otherResp->assertStatus(404);

        // Petugas BISA mengunduh
        $petugasResp = $this->actingAs($this->petugas, 'sanctum')
            ->get("/api/pengajuan/{$nomor}/dokumen");
        $petugasResp->assertStatus(200);
    }

    public function test_guest_cannot_download_document(): void
    {
        $file = UploadedFile::fake()->create('berkas.pdf', 200, 'application/pdf');
        $path = $file->store('private/dokumen_pengajuan');

        $pengajuan = Pengajuan::create([
            'user_id'           => $this->mahasiswa->id,
            'layanan_id'        => $this->layanan->id,
            'nomor_pengajuan'   => 'PM-20261029-GUEST1',
            'nama_mahasiswa'    => 'Mahasiswa 1',
            'nim'               => '11111111',
            'email'             => 'mhs1@test.com',
            'dokumen_path'      => $path,
            'dokumen_nama_asli' => 'berkas.pdf',
            'dokumen_mime'      => 'application/pdf',
            'status'            => 'Menunggu',
        ]);

        // Guest tanpa auth DITOLAK (401)
        $this->getJson("/api/pengajuan/{$pengajuan->nomor_pengajuan}/dokumen")
             ->assertStatus(401);
    }

    // ─── Phase 2: Notifikasi & Audit Log ─────────────────────────────────────

    public function test_status_update_creates_timeline_and_notification(): void
    {
        $submit = $this->actingAs($this->mahasiswa, 'sanctum')
            ->postJson('/api/pengajuan', [
                'layanan_id'     => $this->layanan->id,
                'nama_mahasiswa' => 'Budi Santoso',
                'nim'            => '12345678',
                'email'          => 'budi@test.com',
            ]);
        $nomor = $submit->json('data.nomor_pengajuan');

        // Petugas mengubah status menjadi 'Diproses'
        $updateResp = $this->actingAs($this->petugas, 'sanctum')
            ->patchJson("/api/pengajuan/{$nomor}/status", [
                'status'  => 'Diproses',
                'catatan' => 'Berkas sedang diverifikasi petugas.',
            ]);

        $updateResp->assertStatus(200)
                   ->assertJsonPath('data.status', 'Diproses');

        // Cek bahwa notifikasi masuk ke Mahasiswa
        $notifResp = $this->actingAs($this->mahasiswa, 'sanctum')
            ->getJson('/api/notifications');
        $notifResp->assertStatus(200)
                  ->assertJsonPath('unread_count', 1);

        $notifId = $notifResp->json('notifications.0.id');

        // Mahasiswa menandai notifikasi sebagai sudah dibaca
        $readResp = $this->actingAs($this->mahasiswa, 'sanctum')
            ->patchJson("/api/notifications/{$notifId}/read");
        $readResp->assertStatus(200);

        // Cek unread_count jadi 0
        $notifRespAfter = $this->actingAs($this->mahasiswa, 'sanctum')
            ->getJson('/api/notifications');
        $notifRespAfter->assertJsonPath('unread_count', 0);
    }

    public function test_activity_logs_restricted_to_petugas(): void
    {
        // Mahasiswa tidak bisa akses audit logs -> 403
        $mhsResp = $this->actingAs($this->mahasiswa, 'sanctum')
            ->getJson('/api/admin/activity-logs');
        $mhsResp->assertStatus(403);

        // Petugas bisa akses audit logs -> 200
        $petugasResp = $this->actingAs($this->petugas, 'sanctum')
            ->getJson('/api/admin/activity-logs');
        $petugasResp->assertStatus(200);
    }

    public function test_dashboard_statistics_returns_actual_data(): void
    {
        // Petugas request statistik
        $statResp = $this->actingAs($this->petugas, 'sanctum')
            ->getJson('/api/admin/pengajuan/statistics');
        $statResp->assertStatus(200)
                 ->assertJsonStructure([
                     'pengajuan' => ['total', 'menunggu', 'diproses', 'perlu_perbaikan', 'selesai', 'ditolak', 'terlambat', 'hari_ini'],
                     'blog'      => ['pending', 'published'],
                 ]);
    }

    // ─── Phase 3: Timeline, Revisi, SLA & Ekspor ─────────────────────────────

    public function test_revision_workflow(): void
    {
        $submit = $this->actingAs($this->mahasiswa, 'sanctum')
            ->postJson('/api/pengajuan', [
                'layanan_id'     => $this->layanan->id,
                'nama_mahasiswa' => 'Siti Aminah',
                'nim'            => '87654321',
                'email'          => 'siti@test.com',
            ]);
        $nomor = $submit->json('data.nomor_pengajuan');

        // 1. Petugas meminta revisi dengan alasan wajib
        $reqRev = $this->actingAs($this->petugas, 'sanctum')
            ->postJson("/api/pengajuan/{$nomor}/minta-revisi", [
                'catatan_revisi' => 'Foto KTM buram, mohon unggah ulang yang jelas.',
            ]);
        $reqRev->assertStatus(200)
               ->assertJsonPath('data.status', 'Perlu Perbaikan')
               ->assertJsonPath('data.catatan_revisi', 'Foto KTM buram, mohon unggah ulang yang jelas.');

        // 2. Mahasiswa lain coba kirim revisi -> ditolak (404)
        $fakeFile = UploadedFile::fake()->create('ktm_baru.jpg', 200, 'image/jpeg');
        $mhs2Rev = $this->actingAs($this->mahasiswa2, 'sanctum')
            ->postJson("/api/pengajuan/{$nomor}/revisi", [
                'dokumen' => $fakeFile,
            ]);
        $mhs2Rev->assertStatus(404);

        // 3. Mahasiswa pemilik mengunggah dokumen revisi
        $mhsRev = $this->actingAs($this->mahasiswa, 'sanctum')
            ->postJson("/api/pengajuan/{$nomor}/revisi", [
                'dokumen'    => $fakeFile,
                'keterangan' => 'Ini KTM resolusi tinggi yang sudah jelas.',
            ]);
        $mhsRev->assertStatus(200)
               ->assertJsonPath('data.status', 'Menunggu');

        // 4. Detail pengajuan memiliki timeline lengkap
        $detail = $this->actingAs($this->mahasiswa, 'sanctum')
            ->getJson("/api/pengajuan/{$nomor}");
        $detail->assertStatus(200);
        $histories = $detail->json('histories');
        $this->assertGreaterThanOrEqual(3, count($histories));
    }

    public function test_sla_weekday_calculation(): void
    {
        // Jumat + 3 hari kerja = Rabu minggu berikutnya (lompat Sabtu dan Minggu)
        $friday = Carbon::create(2026, 10, 30, 9, 0, 0); // Pastikan ini Jumat
        $this->assertTrue($friday->isFriday());

        $estimated = Pengajuan::hitungEstimasiSla($friday, 3);
        $this->assertTrue($estimated->isWednesday()); // Sabtu, Minggu dilewati -> Sen (1), Sel (2), Rab (3)
    }

    public function test_export_csv_escapes_formula_injection(): void
    {
        // Buat pengajuan dengan payload potensi CSV Injection
        $this->actingAs($this->mahasiswa, 'sanctum')
            ->postJson('/api/pengajuan', [
                'layanan_id'     => $this->layanan->id,
                'nama_mahasiswa' => '=cmd|"/C calc"!A0',
                'nim'            => '+62812345678',
                'email'          => 'exploit@test.com',
                'keterangan'     => '-2+5+cmd',
            ]);

        // Mahasiswa tidak bisa ekspor -> 403
        $mhsExport = $this->actingAs($this->mahasiswa, 'sanctum')
            ->get('/api/admin/pengajuan/export');
        $mhsExport->assertStatus(403);

        // Petugas bisa ekspor
        $exportResp = $this->actingAs($this->petugas, 'sanctum')
            ->get('/api/admin/pengajuan/export');
        $exportResp->assertStatus(200);

        $csvContent = $exportResp->streamedContent();

        // Formula injection prefixes must be prepended with single quote
        $this->assertStringContainsString("'=cmd", $csvContent);
        $this->assertStringContainsString("'+62812345678", $csvContent);
        $this->assertStringContainsString("'-2+5+cmd", $csvContent);
    }

    public function test_backend_search_filter_and_pagination(): void
    {
        // Create 15 submissions
        for ($i = 1; $i <= 15; $i++) {
            Pengajuan::create([
                'user_id'         => $this->mahasiswa->id,
                'layanan_id'      => $this->layanan->id,
                'nomor_pengajuan' => "PM-20261028-TEST{$i}",
                'nama_mahasiswa'  => "Mahasiswa Nomor {$i}",
                'nim'             => "1000{$i}",
                'email'           => "mhs{$i}@test.com",
                'status'          => $i % 2 === 0 ? 'Menunggu' : 'Diproses',
            ]);
        }

        // Test pagination (page 1, per_page 10)
        $page1 = $this->actingAs($this->petugas, 'sanctum')
            ->getJson('/api/pengajuan?page=1&per_page=10');
        $page1->assertStatus(200)
              ->assertJsonPath('current_page', 1)
              ->assertJsonPath('per_page', 10);
        $this->assertCount(10, $page1->json('data'));

        // Test search
        $search = $this->actingAs($this->petugas, 'sanctum')
            ->getJson('/api/pengajuan?search=10005&page=1');
        $search->assertStatus(200);
        $this->assertCount(1, $search->json('data'));
        $this->assertEquals('10005', $search->json('data.0.nim'));

        // Test status filter
        $filter = $this->actingAs($this->petugas, 'sanctum')
            ->getJson('/api/pengajuan?status=Diproses&page=1&per_page=20');
        $filter->assertStatus(200);
        foreach ($filter->json('data') as $item) {
            $this->assertEquals('Diproses', $item['status']);
        }
    }
}
