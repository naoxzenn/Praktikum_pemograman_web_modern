<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private string $mahasiswaToken;
    private string $petugasToken;
    private User $mahasiswa;
    private User $petugas;
    private User $mahasiswa2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mahasiswa = User::factory()->create([
            'email'    => 'mahasiswa@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'mahasiswa',
        ]);

        $this->petugas = User::factory()->create([
            'email'    => 'petugas@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'petugas',
        ]);

        $this->mahasiswa2 = User::factory()->create([
            'email'    => 'mahasiswa2@test.com',
            'password' => bcrypt('password123'),
            'role'     => 'mahasiswa',
        ]);

        // Ambil token untuk mahasiswa dan petugas
        $this->mahasiswaToken = $this->mahasiswa->createToken('test-mahasiswa')->plainTextToken;
        $this->petugasToken   = $this->petugas->createToken('test-petugas')->plainTextToken;
    }

    // ─── Skenario 1: Login kredensial valid ───────────────────────────────────

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'mahasiswa@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user'])
                 ->assertJsonPath('user.role', 'mahasiswa');

        $this->assertNotEmpty($response->json('token'));
    }

    // ─── Skenario 2: Login password salah → 401 ───────────────────────────────

    public function test_login_with_wrong_password_returns_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'mahasiswa@test.com',
            'password' => 'salah-banget',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_with_nonexistent_email_returns_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email'    => 'tidakada@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    // ─── Skenario 3: GET /api/me tanpa token → 401 ────────────────────────────

    public function test_get_me_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/me');
        $response->assertStatus(401);
    }

    public function test_get_me_with_valid_token_returns_user(): void
    {
        $response = $this->withToken($this->mahasiswaToken)->getJson('/api/me');
        $response->assertStatus(200)
                 ->assertJsonPath('user.email', 'mahasiswa@test.com');
    }

    // ─── Skenario 4: Endpoint petugas-only dengan token mahasiswa → 403 ───────

    public function test_petugas_endpoint_with_mahasiswa_token_returns_403(): void
    {
        // GET /api/pengajuan dilindungi role:petugas
        $response = $this->withToken($this->mahasiswaToken)->getJson('/api/pengajuan');
        $response->assertStatus(403);
    }

    public function test_admin_posts_endpoint_with_mahasiswa_token_returns_403(): void
    {
        $response = $this->withToken($this->mahasiswaToken)->getJson('/api/admin/posts');
        $response->assertStatus(403);
    }

    // ─── Skenario 5: Endpoint mahasiswa-only dengan token petugas → 403 ───────

    public function test_my_posts_endpoint_with_petugas_token_returns_403(): void
    {
        $response = $this->withToken($this->petugasToken)->getJson('/api/my/posts');
        $response->assertStatus(403);
    }

    public function test_submit_post_with_petugas_token_returns_403(): void
    {
        $response = $this->withToken($this->petugasToken)->postJson('/api/posts', [
            'title'   => 'Tes',
            'content' => 'Konten',
        ]);
        $response->assertStatus(403);
    }

    // ─── Skenario 6: Akses data milik mahasiswa lain → 404/403 ───────────────

    public function test_mahasiswa_cannot_access_another_mahasiswas_post(): void
    {
        // Buat post milik mahasiswa2
        $post = \App\Models\Post::factory()->create([
            'user_id' => $this->mahasiswa2->id,
            'status'  => 'draft',
        ]);

        // Mahasiswa1 coba akses detail post mahasiswa2
        $response = $this->withToken($this->mahasiswaToken)
                         ->getJson("/api/my/posts/{$post->id}");

        // Harus 404 (tidak terlihat) atau 403 (ditolak)
        $this->assertContains($response->status(), [403, 404]);
    }

    // ─── Skenario 7: Logout → token tidak bisa dipakai lagi ──────────────────

    public function test_logout_invalidates_token(): void
    {
        // Ambil token baru via login
        $loginResp = $this->postJson('/api/login', [
            'email'    => 'mahasiswa@test.com',
            'password' => 'password123',
        ]);
        $loginResp->assertStatus(200);
        $token = $loginResp->json('token');

        // Logout menggunakan token tersebut
        $logoutResp = $this->withToken($token)->postJson('/api/logout');
        $logoutResp->assertStatus(200);

        // Verifikasi token sudah dihapus dari database
        // Token Sanctum format: "{id}|{hash}" — ambil ID bagian pertama
        $tokenId = explode('|', $token)[0];
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    // ─── Skenario tambahan: Register role tidak bisa dimanipulasi ─────────────

    public function test_register_cannot_set_role_to_petugas(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Coba Manipulasi',
            'email'                 => 'manipulasi@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'petugas', // coba inject role petugas
        ]);

        // Bisa 200 atau 201 (register berhasil), tapi role harus 'mahasiswa'
        if ($response->status() === 200 || $response->status() === 201) {
            $user = User::where('email', 'manipulasi@test.com')->first();
            $this->assertNotNull($user);
            $this->assertEquals('mahasiswa', $user->role);
        }
    }
}
