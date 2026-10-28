<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use App\Services\ContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogPostTest extends TestCase
{
    use RefreshDatabase;

    protected User $mahasiswa1;
    protected User $mahasiswa2;
    protected User $petugas;
    protected PostCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mahasiswa1 = User::factory()->create(['role' => 'mahasiswa']);
        $this->mahasiswa2 = User::factory()->create(['role' => 'mahasiswa']);
        $this->petugas = User::factory()->create(['role' => 'petugas']);

        $this->category = PostCategory::firstOrCreate(
            ['slug' => 'panduan-layanan'],
            ['name' => 'Panduan Layanan', 'badge_color' => '#1e5948']
        );
    }

    public function test_guest_only_sees_published_posts(): void
    {
        // Buat artikel published, pending, dan draft
        Post::create([
            'user_id' => $this->mahasiswa1->id,
            'category_id' => $this->category->id,
            'title' => 'Artikel Published',
            'slug' => 'artikel-published',
            'summary' => 'Ringkasan publik',
            'content' => '<p>Konten aman</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Post::create([
            'user_id' => $this->mahasiswa1->id,
            'category_id' => $this->category->id,
            'title' => 'Artikel Pending',
            'slug' => 'artikel-pending',
            'summary' => 'Ringkasan privat',
            'content' => '<p>Konten rahasia</p>',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/posts');
        $response->assertStatus(200);
        $response->assertJsonFragment(['title' => 'Artikel Published']);
        $response->assertJsonMissing(['title' => 'Artikel Pending']);

        // Akses detail via slug: pending harus 404
        $this->getJson('/api/posts/artikel-pending')->assertStatus(404);
        $this->getJson('/api/posts/artikel-published')->assertStatus(200);
    }

    public function test_mahasiswa_can_create_draft_and_submit_for_review(): void
    {
        // Simpan sebagai draft
        $responseDraft = $this->actingAs($this->mahasiswa1, 'sanctum')->postJson('/api/posts', [
            'title' => 'Tips Belajar Pemrograman Web',
            'category_id' => $this->category->id,
            'summary' => 'Panduan belajar web untuk pemula.',
            'content' => '<p>Konten artikel pemula</p>',
            'action' => 'draft',
        ]);

        $responseDraft->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'title' => 'Tips Belajar Pemrograman Web',
            'status' => 'draft',
            'user_id' => $this->mahasiswa1->id,
        ]);

        $postId = $responseDraft->json('post.id');

        // Submit untuk review
        $responseSubmit = $this->actingAs($this->mahasiswa1, 'sanctum')->postJson("/api/my/posts/{$postId}/submit");
        $responseSubmit->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'status' => 'pending',
        ]);
    }

    public function test_mahasiswa_cannot_publish_directly_via_request_payload(): void
    {
        $response = $this->actingAs($this->mahasiswa1, 'sanctum')->postJson('/api/posts', [
            'title' => 'Percobaan Bypass Publikasi',
            'category_id' => $this->category->id,
            'summary' => 'Mencoba publikasi ilegal.',
            'content' => '<p>Isi artikel</p>',
            'action' => 'publish', // tidak valid untuk mahasiswa
            'status' => 'published',
        ]);

        $response->assertStatus(422); // 'action' hanya in:draft,submit
    }

    public function test_mahasiswa_cannot_read_or_edit_another_mahasiswa_post(): void
    {
        $post = Post::create([
            'user_id' => $this->mahasiswa1->id,
            'category_id' => $this->category->id,
            'title' => 'Artikel Rahasia Mahasiswa 1',
            'slug' => 'artikel-rahasia-mahasiswa-1',
            'summary' => 'Rahasia',
            'content' => '<p>Konten</p>',
            'status' => 'draft',
        ]);

        // Mahasiswa 2 mencoba membaca detail
        $this->actingAs($this->mahasiswa2, 'sanctum')
            ->getJson("/api/my/posts/{$post->id}")
            ->assertStatus(404);

        // Mahasiswa 2 mencoba mengubah
        $this->actingAs($this->mahasiswa2, 'sanctum')
            ->patchJson("/api/my/posts/{$post->id}", [
                'title' => 'Diedit oleh hacker',
                'category_id' => $this->category->id,
                'summary' => 'Hacked',
                'content' => '<p>Hacked</p>',
            ])
            ->assertStatus(404);
    }

    public function test_petugas_can_review_approve_and_reject_posts(): void
    {
        $post = Post::create([
            'user_id' => $this->mahasiswa1->id,
            'category_id' => $this->category->id,
            'title' => 'Pengajuan Artikel Mahasiswa',
            'slug' => 'pengajuan-artikel-mahasiswa',
            'summary' => 'Artikel menunggu telaah',
            'content' => '<p>Isi artikel</p>',
            'status' => 'pending',
        ]);

        // Petugas cek antrean pending
        $this->actingAs($this->petugas, 'sanctum')
            ->getJson('/api/admin/posts/pending')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'Pengajuan Artikel Mahasiswa']);

        // Petugas menolak tanpa alasan -> validasi gagal
        $this->actingAs($this->petugas, 'sanctum')
            ->postJson("/api/admin/posts/{$post->id}/reject", [])
            ->assertStatus(422);

        // Petugas menolak dengan alasan wajib
        $rejectResponse = $this->actingAs($this->petugas, 'sanctum')
            ->postJson("/api/admin/posts/{$post->id}/reject", [
                'reason' => 'Mohon tambahkan referensi akademik yang valid pada paragraf kedua.',
            ]);

        $rejectResponse->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'status' => 'rejected',
            'rejection_reason' => 'Mohon tambahkan referensi akademik yang valid pada paragraf kedua.',
            'reviewed_by' => $this->petugas->id,
        ]);
        $this->assertDatabaseHas('post_reviews', [
            'post_id' => $post->id,
            'decision' => 'rejected',
        ]);

        // Mahasiswa membaca alasan penolakan pada artikelnya
        $myPostResponse = $this->actingAs($this->mahasiswa1, 'sanctum')
            ->getJson("/api/my/posts/{$post->id}");
        $myPostResponse->assertStatus(200)
            ->assertJsonFragment(['rejection_reason' => 'Mohon tambahkan referensi akademik yang valid pada paragraf kedua.']);

        // Mahasiswa memperbaiki dan submit ulang
        $resubmitResponse = $this->actingAs($this->mahasiswa1, 'sanctum')
            ->patchJson("/api/my/posts/{$post->id}", [
                'title' => 'Pengajuan Artikel Mahasiswa (Revisi)',
                'category_id' => $this->category->id,
                'summary' => 'Artikel sudah diperbaiki',
                'content' => '<p>Isi artikel dengan referensi lengkap.</p>',
                'action' => 'submit',
            ]);

        $resubmitResponse->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'status' => 'pending',
            'rejection_reason' => null,
        ]);

        // Petugas menyetujui artikel (approve)
        $approveResponse = $this->actingAs($this->petugas, 'sanctum')
            ->postJson("/api/admin/posts/{$post->id}/approve", [
                'notes' => 'Artikel sangat baik dan layak dipublikasikan.',
            ]);

        $approveResponse->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'status' => 'published',
            'reviewed_by' => $this->petugas->id,
        ]);
        $this->assertDatabaseHas('post_reviews', [
            'post_id' => $post->id,
            'decision' => 'approved',
        ]);

        // Sekarang artikel muncul di publik
        $this->getJson('/api/posts')->assertJsonFragment(['title' => 'Pengajuan Artikel Mahasiswa (Revisi)']);
    }

    public function test_petugas_can_create_and_directly_publish_announcement(): void
    {
        $response = $this->actingAs($this->petugas, 'sanctum')->postJson('/api/admin/posts', [
            'title' => 'Pengumuman Libur Akademik Nasional',
            'category_id' => $this->category->id,
            'summary' => 'Pemberitahuan hari libur perkuliahan.',
            'content' => '<p>Kegiatan perkuliahan diliburkan mulai besok.</p>',
            'action' => 'publish',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'title' => 'Pengumuman Libur Akademik Nasional',
            'status' => 'published',
            'user_id' => $this->petugas->id,
        ]);

        // Langsung dapat diakses di publik
        $this->getJson('/api/posts')->assertJsonFragment(['title' => 'Pengumuman Libur Akademik Nasional']);
    }

    public function test_content_sanitizer_blocks_xss_and_scripts(): void
    {
        $malicious = '<p>Halo dunia</p><script>alert("XSS")</script><img src="x" onerror="alert(1)"><a href="javascript:alert(2)">Klik</a>';
        $cleaned = ContentSanitizer::clean($malicious);

        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringNotContainsString('onerror', $cleaned);
        $this->assertStringNotContainsString('javascript:', $cleaned);
        $this->assertStringContainsString('<p>Halo dunia</p>', $cleaned);
    }

    public function test_duplicate_slug_handling(): void
    {
        $slug1 = Post::generateUniqueSlug('Judul Sama');
        Post::create([
            'user_id' => $this->mahasiswa1->id,
            'category_id' => $this->category->id,
            'title' => 'Judul Sama',
            'slug' => $slug1,
            'summary' => 'Ringkasan 1',
            'content' => '<p>Konten</p>',
            'status' => 'published',
        ]);

        $slug2 = Post::generateUniqueSlug('Judul Sama');
        $this->assertNotEquals($slug1, $slug2);
        $this->assertEquals('judul-sama-1', $slug2);
    }
}
