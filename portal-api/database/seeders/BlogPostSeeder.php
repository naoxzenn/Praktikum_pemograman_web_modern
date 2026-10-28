<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $petugas = User::where('role', 'petugas')->first();
        $mahasiswa = User::where('role', 'mahasiswa')->first();

        $catPanduan = PostCategory::where('slug', 'panduan-layanan')->first();
        $catAkademik = PostCategory::where('slug', 'akademik')->first();
        $catBeasiswa = PostCategory::where('slug', 'beasiswa')->first();
        $catKemahasiswaan = PostCategory::where('slug', 'kemahasiswaan')->first();

        // 1. Pengumuman / Panduan resmi oleh Petugas (Published)
        if ($catPanduan && $petugas) {
            Post::firstOrCreate(
                ['slug' => 'panduan-lengkap-pengajuan-surat-keterangan-mahasiswa-aktif-2026'],
                [
                    'user_id' => $petugas->id,
                    'category_id' => $catPanduan->id,
                    'title' => 'Panduan Lengkap Pengajuan Surat Keterangan Mahasiswa Aktif 2026',
                    'summary' => 'Langkah-langkah cepat mengajukan surat aktif kuliah secara digital melalui portal mahasiswa tanpa perlu antre di loket.',
                    'content' => '<p>Surat Keterangan Mahasiswa Aktif merupakan dokumen resmi yang membuktikan status akademik seorang mahasiswa pada semester berjalan.</p><p>Melalui Portal Layanan Mahasiswa, pengajuan dapat dilakukan dengan mudah: cukup pastikan Anda telah terdaftar dan login, pilih menu <strong>Daftar Layanan</strong>, lalu pilih <em>Surat Keterangan Mahasiswa Aktif</em>.</p><p>Sertakan tujuan pengajuan (seperti pengurusan BPJS, beasiswa luar kampus, atau tunjangan orang tua), lalu pantau perkembangannya melalui menu <strong>Lacak Status</strong>.</p><blockquote>Waktu pemrosesan standar petugas adalah 1-2 hari kerja.</blockquote>',
                    'status' => 'published',
                    'reviewed_by' => $petugas->id,
                    'published_at' => now()->subDays(3),
                ]
            );
        }

        // 2. Beasiswa resmi (Published)
        if ($catBeasiswa && $petugas) {
            Post::firstOrCreate(
                ['slug' => 'pembukaan-pendaftaran-beasiswa-prestasi-akademik-semester-genap'],
                [
                    'user_id' => $petugas->id,
                    'category_id' => $catBeasiswa->id,
                    'title' => 'Pembukaan Pendaftaran Beasiswa Prestasi Akademik Semester Genap',
                    'summary' => 'Informasi persyaratan, kuota penerima, dan batas akhir pendaftaran beasiswa prestasi untuk mahasiswa aktif minimal semester 3.',
                    'content' => '<p>Biro Kemahasiswaan membuka pendaftaran <strong>Beasiswa Prestasi Akademik</strong> tahun ajaran 2026/2027.</p><p>Persyaratan utama mencakup: IPK minimal 3.50, tidak sedang menerima beasiswa lain, dan aktif dalam organisasi kampus atau memiliki sertifikat prestasi perlombaan.</p><p>Pendaftaran dibuka mulai 1 November hingga 20 November 2026. Berkas pendukung dapat diunggah melalui formulir permohonan layanan beasiswa di portal ini.</p>',
                    'status' => 'published',
                    'reviewed_by' => $petugas->id,
                    'published_at' => now()->subDays(5),
                ]
            );
        }

        // 3. Kalender Akademik (Published)
        if ($catAkademik && $petugas) {
            Post::firstOrCreate(
                ['slug' => 'kalender-akademik-batas-akhir-pembayaran-ukt-dan-pengisian-krs'],
                [
                    'user_id' => $petugas->id,
                    'category_id' => $catAkademik->id,
                    'title' => 'Kalender Akademik: Batas Akhir Pembayaran UKT & Pengisian KRS',
                    'summary' => 'Pemberitahuan penting batas waktu dispensasi UKT serta jadwal asistensi pengisian KRS online bersama dosen pembimbing akademik.',
                    'content' => '<p>Mahasiswa diharapkan memperhatikan tenggat waktu pembayaran UKT semester depan agar status akademik tetap aktif.</p><p>Bagi mahasiswa yang membutuhkan pengajuan perpanjangan atau keringanan pembayaran, silakan ajukan permohonan dispensasi melalui layanan keuangan sebelum tanggal 10 bulan depan.</p><p>Konsultasi Kartu Rencana Studi (KRS) dengan Dosen Pembimbing Akademik dijadwalkan secara daring dan luring sesuai jadwal prodi masing-masing.</p>',
                    'status' => 'published',
                    'reviewed_by' => $petugas->id,
                    'published_at' => now()->subDays(9),
                ]
            );
        }

        // 4. Artikel Mahasiswa berstatus PENDING (Menunggu review petugas)
        if ($catKemahasiswaan && $mahasiswa) {
            Post::firstOrCreate(
                ['slug' => 'pengalaman-mengikuti-kompetisi-kreativitas-mahasiswa-tingkat-nasional'],
                [
                    'user_id' => $mahasiswa->id,
                    'category_id' => $catKemahasiswaan->id,
                    'title' => 'Pengalaman Mengikuti Kompetisi Kreativitas Mahasiswa Tingkat Nasional',
                    'summary' => 'Catatan dan tips persiapan proposal inovasi teknologi dari tim mahasiswa perwakilan kampus hingga lolos tahap pendanaan.',
                    'content' => '<p>Mengikuti ajang Program Kreativitas Mahasiswa (PKM) merupakan salah satu pengalaman paling berharga selama masa kuliah.</p><p>Berikut adalah 3 tips utama dalam menyusun proposal:</p><ul><li><strong>Pilih isu riil di masyarakat:</strong> Topik yang solutif terhadap permasalahan lingkungan atau sosial selalu memiliki nilai tambah.</li><li><strong>Bimbingan intensif:</strong> Diskusikan draft ide secara berkala dengan dosen pembimbing.</li><li><strong>Format sesuai pedoman:</strong> Pastikan seluruh berkas administrasi dan tata letak sesuai panduan resmi.</li></ul><p>Semoga artikel ini dapat menginspirasi teman-teman mahasiswa lainnya untuk turut berkarya!</p>',
                    'status' => 'pending',
                ]
            );
        }
    }
}

