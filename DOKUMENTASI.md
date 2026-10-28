# Dokumentasi Perubahan & Pengembangan Portal Layanan Mahasiswa

Dokumen ini mencatat seluruh perbaikan bug, penambahan fitur baru, dan struktur arsitektur yang telah diimplementasikan pada proyek **Portal Layanan Mahasiswa** (Frontend Nuxt 4 & Backend Laravel 13).

---

## 📁 Lokasi Dokumentasi & Laporan
- **Dokumentasi Proyek Lengkap:** Tersedia langsung di file ini: `D:\Projek pengembangan web\DOKUMENTASI.md`.
- **Laporan Audit Autentikasi:** `audit_auth_report.md` di brain/artifacts.
- **Pengujian Backend:**
  - Blog & Moderasi: `portal-api/tests/Feature/BlogPostTest.php` (10 tests).
  - Autentikasi & Otorisasi: `portal-api/tests/Feature/AuthenticationTest.php` (12 tests).
  - Keamanan & Peningkatan Layanan (Phase 1-3): `portal-api/tests/Feature/PengajuanEnhancementTest.php` (10 tests).

---

## 🏛️ Arsitektur Sistem Blog CMS, Kontributor Mahasiswa & Moderasi Petugas

Sistem Blog CMS menghubungkan publik, mahasiswa kontributor, dan petugas moderator dalam satu ekosistem terpadu.

```
Publik / Guest ───> GET /api/posts (Hanya Published)
                      │
Mahasiswa ─────────> Tulis di Tiptap ──> POST /api/posts (Status: draft / pending)
                      │
                      ▼
Petugas ───────────> Antrean Review ───> POST /api/admin/posts/{id}/approve ──> Status: published
                      │             └──> POST /api/admin/posts/{id}/reject  ──> Status: rejected (Alasan Wajib)
                      ▼
Petugas ───────────> Buat Pengumuman ──> POST /api/admin/posts (Status: published langsung)
```

---

## 🛠️ Ringkasan Perubahan Berdasarkan Komponen

### 1. Database & Migrations (Laravel — `portal-api`)
- `2026_10_27_100000_create_post_categories_table.php`:
  Tabel `post_categories` (id, name, slug, badge_color, timestamps). Dilengkapi data awal: Panduan Layanan, Akademik, Beasiswa, Kemahasiswaan, Pengumuman.
- `2026_10_27_100100_create_posts_table.php`:
  Tabel `posts` (id, user_id, category_id, title, slug, summary, content, cover_image_path, status ['draft', 'pending', 'published', 'rejected'], reviewed_by, rejection_reason, published_at, timestamps).
- `2026_10_27_100200_create_post_reviews_table.php`:
  Tabel `post_reviews` (id, post_id, reviewer_id, decision ['approved', 'rejected'], notes, timestamps).
- `2026_10_28_200000_add_sla_to_layanans_table.php`:
  Menambahkan kolom `sla_hari_kerja` (default: 3) pada tabel `layanans` untuk estimasi hari kerja penyelesaian layanan.
- `2026_10_28_200100_add_kolom_to_pengajuans_table.php`:
  Menambahkan kolom `keterangan`, `dokumen_path`, `dokumen_nama_asli`, `dokumen_mime`, `catatan_revisi`, `estimasi_selesai`, dan `diproses_oleh` pada tabel `pengajuans`.
- `2026_10_28_200200_create_pengajuan_histories_table.php`:
  Tabel `pengajuan_histories` (id, pengajuan_id, user_id, status_sebelumnya, status_baru, catatan, created_at) sebagai audit trail timeline permanen perubahan status pengajuan.
- `2026_10_28_200300_create_notifications_table.php`:
  Tabel `notifications` (id, user_id, type, title, message, data, read_at, timestamps) untuk notifikasi internal pengguna.
- `2026_10_28_200400_create_activity_logs_table.php`:
  Tabel `activity_logs` (id, user_id, role, action, subject_type, subject_id, description, properties, ip_address, created_at) untuk mencatat aktivitas penting operasional tanpa menyimpan rahasia.

### 2. Backend Models, Services & Controllers (`portal-api`)
- **Eloquent Models:**
  - [app/Models/PostCategory.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Models/PostCategory.php): Relasi `hasMany(Post)`.
  - [app/Models/Post.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Models/Post.php): Relasi `author()`, `category()`, `reviewer()`, `reviews()`. Method helper `Post::generateUniqueSlug()`.
  - [app/Models/PostReview.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Models/PostReview.php): Relasi `post()`, `reviewer()`.
  - [app/Models/Layanan.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Models/Layanan.php): Kolom `sla_hari_kerja`.
  - [app/Models/Pengajuan.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Models/Pengajuan.php): Relasi `layanan()`, `user()`, `diprosesOleh()`, `histories()`. Method `hitungEstimasiSla()` (hitung hari kerja tanpa Sabtu/Minggu) dan `generateUniqueNomor()` (collision safe).
  - [app/Models/PengajuanHistory.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Models/PengajuanHistory.php): Relasi `pengajuan()`, `user()`.
  - [app/Models/Notification.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Models/Notification.php): Helper `createNotification()` dan `markAsRead()`.
  - [app/Models/ActivityLog.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Models/ActivityLog.php): Helper `record()` pencatatan audit trail aman.
- **Sanitasi HTML Rich Text:**
  - [app/Services/ContentSanitizer.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Services/ContentSanitizer.php):
    Whitelist tag HTML aman, membersihkan script dan protokol XSS berbahaya.
- **Controllers & API:**
  - [app/Http/Controllers/Api/PengajuanController.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Http/Controllers/Api/PengajuanController.php):
    - `GET /api/pengajuan`: Paginasi backend, pencarian (nama, NIM, nomor, layanan), dan filter status.
    - `POST /api/pengajuan`: Pengajuan baru dengan validasi upload berkas (PDF/JPG/PNG maks 5MB) ke storage privat, perhitungan SLA hari kerja, pembuatan riwayat awal, dan notifikasi ke petugas.
    - `GET /api/pengajuan/{id}`: Detail pengajuan beserta timeline riwayat status (terisolasi untuk mahasiswa pemilik, terbuka untuk petugas).
    - `PATCH /api/pengajuan/{id}/status`: Pembaruan status oleh petugas, pencatatan ke timeline, notifikasi mahasiswa, dan log aktivitas.
    - `POST /api/pengajuan/{id}/minta-revisi`: Permintaan perbaikan dokumen oleh petugas dengan alasan wajib (minimal 5 karakter), status `Perlu Perbaikan`.
    - `POST /api/pengajuan/{id}/revisi`: Pengunggahan dokumen revisi pengganti oleh mahasiswa pemilik, status kembali menjadi `Menunggu`.
    - `GET /api/pengajuan/{id}/dokumen`: Unduh dokumen privat dengan pengecekan kepemilikan dan proteksi *path traversal*.
    - `GET /api/admin/pengajuan/statistics`: Agregasi statistik aktual dari database (total, per status, pengajuan lewat SLA, hari ini, blog pending).
    - `GET /api/admin/pengajuan/export`: Ekspor CSV laporan pengajuan yang aman dari *CSV Formula Injection* (prefix kutip tunggal).
  - [app/Http/Controllers/Api/NotificationController.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Http/Controllers/Api/NotificationController.php):
    - `GET /api/notifications`: Daftar notifikasi dan jumlah belum dibaca pengguna.
    - `PATCH /api/notifications/{id}/read`: Tandai satu notifikasi sudah dibaca.
    - `POST /api/notifications/mark-all-read`: Tandai semua notifikasi sudah dibaca.
  - [app/Http/Controllers/Api/ActivityLogController.php](file:///d:/Projek%20pengembangan%20web/portal-api/app/Http/Controllers/Api/ActivityLogController.php):
    - `GET /api/admin/activity-logs`: Riwayat audit log aktivitas khusus petugas.
    - `POST /api/posts` (Mahasiswa, buat draft atau kirim review)
    - `PATCH /api/my/posts/{id}` (Mahasiswa, edit draft atau revisi naskah rejected)
    - `POST /api/my/posts/{id}/submit` (Mahasiswa, kirim ulang artikel)
    - [app/Http/Controllers/Api/AdminPostController.php](file:///d:/Projek%20pengembangan%20web/  portal-api/app/Http/Controllers/Api/AdminPostController.php):
    - `GET /api/admin/posts` (Petugas, semua artikel dengan filter & paginasi)
    - `GET /api/admin/posts/pending` (Petugas, antrean telaah artikel mahasiswa)
    - `POST /api/admin/posts` (Petugas, terbitkan pengumuman resmi langsung / simpan draft)
    - `POST /api/admin/posts/{id}/approve` (Petugas, setujui naskah -> published)
    - `POST /api/admin/posts/{id}/reject` (Petugas, tolak naskah dengan alasan wajib)
    - `POST /api/admin/posts/{id}/publish` (Petugas, publikasikan draft)
    - `POST /api/admin/posts/{id}/archive` (Petugas, arsipkan artikel)
    - `GET /api/admin/posts/{id}/reviews` (Petugas, riwayat catatan moderasi)

### 3. Autentikasi, CORS & Endpoint Root Backend (`portal-api`)
- **Konfigurasi CORS:**
  - [config/cors.php](file:///d:/Projek%20pengembangan%20web/portal-api/config/cors.php):
    Dipublish secara eksplisit untuk menangani request cross-origin dari frontend Nuxt 4 (`http://localhost:3000`). Jalur `api/*` dan `sanctum/csrf-cookie` terbuka dengan `supports_credentials => false` (arsitektur token Bearer Sanctum).
- **Endpoint Root API:**
  - [routes/web.php](file:///d:/Projek%20pengembangan%20web/portal-api/routes/web.php):
    Mengganti tampilan default template Blade `view('welcome')` menjadi respons JSON health-check (`{"status": "ok", "message": "Portal Mahasiswa API berjalan.", "version": "1.0"}`). Aman dari kebocoran informasi lingkungan internal.
- **Database Model Factories:**
  - [database/factories/PostFactory.php](file:///d:/Projek%20pengembangan%20web/portal-api/database/factories/PostFactory.php) & [database/factories/PostCategoryFactory.php](file:///d:/Projek%20pengembangan%20web/portal-api/database/factories/PostCategoryFactory.php):
    Dibuat untuk kebutuhan automated unit & feature testing.

### 4. Frontend Nuxt 4 & Rich Text Tiptap (`Projek_Web`)
- **Dependensi Tiptap Terpasang:**
  - `@tiptap/vue-3`, `@tiptap/pm`, `@tiptap/starter-kit`, `@tiptap/extension-link`, `@tiptap/extension-image`, `@tiptap/extension-placeholder`, `@tiptap/extension-underline`.
- **Komponen Editor:**
  - [app/components/TiptapEditor.vue](file:///d:/Projek%20pengembangan%20web/Projek_Web/app/components/TiptapEditor.vue):
    Editor WYSIWYG dibungkus `<ClientOnly>`, toolbar lengkap (Paragraph, Heading 1-3, Bold, Italic, Underline, Strikethrough, Bullet List, Numbered List, Blockquote, Divider, Hyperlink modal, Image uploader, Undo/Redo, Clear Format). Tombol toolbar reaktif dengan status aktif kursor.
- **Halaman Blog & Navigasi:**
  - [app/pages/blog/index.vue](file:///d:/Projek%20pengembangan%20web/Projek_Web/app/pages/blog/index.vue):
    Daftar artikel publik dinamis terhubung ke API Laravel, tab kategori, pencarian real-time, penomoran halaman (pagination), tombol aksi cepat kontributor & petugas.
  - [app/pages/blog/[slug].vue](file:///d:/Projek%20pengembangan%20web/Projek_Web/app/pages/blog/[slug].vue):
    Halaman detail artikel publik berbasis slug, tipografi naskah, cover image, informasi penulis, dan summary callout.
  - [app/pages/blog/tulis.vue](file:///d:/Projek%20pengembangan%20web/Projek_Web/app/pages/blog/tulis.vue):
    Formulir penulisan artikel untuk mahasiswa (Simpan Draft / Kirim Review) dan petugas (Simpan Draft / Terbitkan Langsung). Dilengkapi slug preview otomatis dan cover image uploader.
  - [app/pages/blog/artikel-saya/index.vue](file:///d:/Projek%20pengembangan%20web/Projek_Web/app/pages/blog/artikel-saya/index.vue):
    Dashboard kontributor mahasiswa: status badge naskah, callout catatan penolakan jika ditolak, tombol edit dan kirim ulang review.
  - [app/pages/blog/artikel-saya/[id].vue](file:///d:/Projek%20pengembangan%20web/Projek_Web/app/pages/blog/artikel-saya/[id].vue):
    Formulir revisi artikel mahasiswa dengan tampilan catatan evaluasi petugas sebelumnya.
  - [app/pages/pengajuan.vue](file:///d:/Projek%20pengembangan%20web/Projek_Web/app/pages/pengajuan.vue):
    Formulir pengajuan layanan mahasiswa dengan pengiriman `FormData`: input lampiran dokumen (PDF/JPG/PNG maks 5MB), keterangan tambahan, dan ringkasan estimasi hari kerja SLA sebelum submit.
  - [app/pages/status.vue](file:///d:/Projek%20pengembangan%20web/Projek_Web/app/pages/status.vue):
    Pelacakan pengajuan komprehensif: tampilan kartu hasil, badge status, estimasi tanggal penyelesaian SLA (indikator tepat waktu vs terlambat), tombol unduh berkas lampiran, formulir unggah berkas revisi pengganti (jika status *Perlu Perbaikan*), timeline riwayat status berstempel waktu, dan tombol cetak bukti pengajuan (`window.print()`).
  - [app/pages/admin.vue](file:///d:/Projek%20pengembangan%20web/Projek_Web/app/pages/admin.vue):
    Dashboard petugas multi-tab terintegrasi:
    - Tab 1 (Pengajuan): Ringkasan statistik real-time dari database, pencarian real-time, filter status, paginasi backend, tombol unduh berkas dokumen mahasiswa, dropdown pembaruan status, modal permintaan revisi dokumen dengan alasan wajib, dan tombol ekspor CSV laporan.
    - Tab 2 (Blog): Statistik blog, antrean review naskah mahasiswa dengan dialog pemeriksaan lengkap, tombol setujui, serta dialog penolakan dengan alasan wajib.
    - Tab 3 (Log Audit): Tabel audit trail aktivitas petugas & mahasiswa berstempel waktu dan IP address.
  - [app/app.vue](file:///d:/Projek%20pengembangan%20web/Projek_Web/app/app.vue):
    Navigasi utama dilengkapi lonceng notifikasi real-time dengan counter unread badge, popover daftar pemberitahuan masuk (pembaruan status pengajuan, revisi dokumen, moderasi blog), dan tombol "Tandai dibaca".

---

## 🔒 Kebijakan Keamanan, Prosedur Backup & Alur Bisnis

1. **Publik:**
   Hanya artikel berstatus `published` yang ditampilkan di endpoint publik `/api/posts` dan `/api/posts/{slug}`.
2. **Mahasiswa:**
   - Dilarang mengirim status `published` atau `reviewed_by` via request payload (dicegah di backend).
   - Hanya dapat mengakses dan mengedit artikel serta pengajuan miliknya sendiri (`user_id`).
   - Berkas lampiran dan riwayat pengajuan milik mahasiswa lain diisolasi (HTTP 404/403).
   - Pengajuan awal berstatus `Menunggu`, dan pengajuan yang berstatus `Perlu Perbaikan` dapat diperbaiki dengan mengunggah berkas pengganti via endpoint revisi.
3. **Petugas:**
   - Hak administratif dan editorial penuh: verifikasi berkas, persetujuan/penolakan naskah, permintaan perbaikan berkas, dan publikasi pengumuman resmi.
   - Semua aksi status menggunakan database transaction (`DB::transaction`), mencatat riwayat timeline (`pengajuan_histories`), audit trail (`activity_logs`), dan notifikasi (`notifications`).
4. **Keamanan Dokumen & Unggahan:**
   - Berkas pengajuan disimpan di storage privat (`storage/app/private/dokumen_pengajuan/`), tidak dapat diakses secara langsung dari URL web publik.
   - Akses berkas hanya melalui endpoint streaming `GET /api/pengajuan/{id}/dokumen` dengan pengecekan kepemilikan token dan proteksi path traversal (`..`).
   - Batas file maksimal 5MB dengan verifikasi MIME type ketat (`application/pdf`, `image/jpeg`, `image/png`, `image/jpg`).
5. **Keamanan Ekspor CSV (Anti Formula Injection):**
   - Karakter berbahaya pemicu eksekusi formula Excel/DDE (`=`, `+`, `-`, `@`, `\t`, `\r`) pada data input mahasiswa otomatis di-escape dengan prefix kutip tunggal (`'`).
6. **Perhitungan Estimasi SLA:**
   - Dihitung secara presisi berdasarkan hari kerja (Senin s.d. Jumat), melompati akhir pekan (Sabtu & Minggu).
7. **Prosedur Backup & Restore Database:**
   - **Backup MySQL Produksi / Development:**
     ```bash
     # Format nama backup unik berstempel waktu
     mysqldump -u root -p portal_mahasiswa > "backup_portal_mahasiswa_$(date +%Y%m%d_%H%M%S).sql"
     ```
   - **Restore Aman:**
     Uji restore terlebih dahulu pada database pengujian terpisah, jangan langsung menimpa database aktif:
     ```bash
     mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS portal_mahasiswa_test;"
     mysql -u root -p portal_mahasiswa_test < backup_portal_mahasiswa_YYYYMMDD_HHMMSS.sql
     ```
   - **Prinsip Keselamatan Data:** Dilarang menggunakan `migrate:fresh` atau `db:wipe` pada database aktif. Semua migrasi baru bersifat additif (`Schema::table` dengan nullable/default value).

---

## 🧪 Hasil Pengujian (Automated & Manual)

### 1. Backend Feature Tests — Blog CMS (`BlogPostTest.php`)
Dijalankan menggunakan PHPUnit di lingkungan testing Laravel:
```bash
cd portal-api
php artisan test --filter=BlogPostTest
```
**Hasil:** `tests: 10, passed: 10, assertions: 37` (100% Lulus)

### 2. Backend Feature Tests — Autentikasi & Otorisasi (`AuthenticationTest.php`)
Dijalankan menggunakan PHPUnit di lingkungan testing Laravel:
```bash
cd portal-api
php artisan test --filter=AuthenticationTest
```
**Hasil:** `tests: 12, passed: 12, assertions: 20` (100% Lulus)

### 3. Backend Feature Tests — Peningkatan Layanan & Keamanan Phase 1–3 (`PengajuanEnhancementTest.php`)
Dijalankan menggunakan PHPUnit di lingkungan testing Laravel:
```bash
cd portal-api
php artisan test --filter=PengajuanEnhancementTest
```
**Hasil:** `tests: 10, passed: 10, assertions: 65` (100% Lulus)
- `test_mahasiswa_can_submit_with_valid_document` (Lulus)
- `test_upload_rejects_dangerous_file_type` (Lulus)
- `test_document_download_ownership_authorization` (Lulus)
- `test_status_update_creates_timeline_and_notification` (Lulus)
- `test_activity_logs_restricted_to_petugas` (Lulus)
- `test_dashboard_statistics_returns_actual_data` (Lulus)
- `test_revision_workflow` (Lulus)
- `test_sla_weekday_calculation` (Lulus)
- `test_export_csv_escapes_formula_injection` (Lulus)
- `test_backend_search_filter_and_pagination` (Lulus)

> **Ringkasan Pengujian Backend Keseluruhan:**  
> `php artisan test` ➔ **32 tests passed (100%), 122 assertions, 0 failed**.

### 4. Frontend Nuxt Build Test
Dijalankan menggunakan Nitro / Vite:
```bash
cd Projek_Web
npx nuxi build
```
**Hasil:** `✨ Build complete in 41.5s!` (Zero syntax error, Zero typescript error, Zero lint error).

---

## 🚀 Panduan Menjalankan Aplikasi

1. **Jalankan Backend Laravel:**
   ```bash
   cd portal-api
   php artisan serve
   ```
   API berjalan di `http://127.0.0.1:8000`.

2. **Jalankan Frontend Nuxt:**
   ```bash
   cd Projek_Web
   npm run dev
   ```
   Aplikasi berjalan di `http://localhost:3000`.

3. **Akun Uji Coba Tersedia:**
   - **Mahasiswa (Kontributor):** `tester@example.com` / `password`
   - **Petugas (Editor & Moderator):** `petugas@example.com` / `password`

