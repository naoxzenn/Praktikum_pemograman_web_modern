# Portal Layanan Mahasiswa & Blog CMS

Aplikasi web terpadu untuk pelayanan administrasi mahasiswa serta sistem publikasi blog/warta kampus dengan kontributor mahasiswa dan moderasi petugas.

## Stack Teknologi
- **Frontend:** Nuxt 4 (`4.6.1`), Vue 3 (`3.5.44`), TypeScript, Vite, Tiptap (`^3.32.0`).
- **Backend:** Laravel 13 (`13.17`), PHP 8.4, Laravel Sanctum (`4.0`), MySQL.
- **Autentikasi & Otorisasi:** Sanctum API Token + Role Middleware (`mahasiswa`, `petugas`).

---

## Fitur Utama
1. **Layanan Mahasiswa:**
   - Pengajuan surat keterangan, dispensasi, legalisir, dan beasiswa.
   - Pelacakan status nomor pengajuan.
   - Manajemen status dan verifikasi oleh petugas.
2. **Blog CMS & Warta Kampus:**
   - **Publik:** Melihat daftar dan detail artikel published, filter kategori, pencarian, dan paginasi.
   - **Kontributor Mahasiswa:** Menulis artikel via Tiptap WYSIWYG editor, menyimpan draft, mengirim naskah ke antrean telaah petugas, melihat catatan revisi, dan mengirim ulang artikel yang ditolak.
   - **Moderasi & Editor Petugas:** Antrean review artikel mahasiswa, persetujuan publikasi (approve), penolakan naskah dengan alasan wajib (reject), serta pembuatan dan penerbitan langsung pengumuman resmi.
   - **Keamanan:** Sanitasi HTML backend via DOMDocument whitelist (anti-XSS), validasi unggah media (MIME, hash filename, maks 2MB), proteksi role & kepemilikan.

---

## Menjalankan Proyek

### 1. Backend (`portal-api`)
```bash
cd portal-api
composer install
php artisan migrate
php artisan db:seed --class=BlogPostSeeder
php artisan storage:link
php artisan serve
```
Backend berjalan pada: `http://127.0.0.1:8000`

Menjalankan pengujian:
```bash
php artisan test
```

### 2. Frontend (`Projek_Web`)
```bash
cd Projek_Web
npm install
npm run dev
```
Frontend berjalan pada: `http://localhost:3000`

---

## Dokumentasi Lengkap
Lihat [DOKUMENTASI.md](file:///d:/Projek%20pengembangan%20web/DOKUMENTASI.md) untuk detail arsitektur, rincian tabel database, daftar endpoint API, dan alur moderasi.

