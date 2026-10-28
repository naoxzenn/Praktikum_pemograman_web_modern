<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LayananController;
use App\Http\Controllers\Api\PengajuanController;
use App\Http\Controllers\Api\PostCategoryController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\AdminPostController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ActivityLogController;

// ── Auth publik ──────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// ── Layanan publik (hanya baca) ──────────────────────────────────────────
Route::get('/layanan', [LayananController::class, 'index']);
Route::get('/layanan/{layanan}', [LayananController::class, 'show']);

// ── Blog publik (hanya artikel published) ────────────────────────────────
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{slug}', [PostController::class, 'show']);
Route::get('/post-categories', [PostCategoryController::class, 'index']);

// ── Endpoint terautentikasi ──────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Notifikasi sistem (tersedia untuk mahasiswa & petugas)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // Unduh dokumen lampiran aman (dicek kepemilikan di controller)
    Route::get('/pengajuan/{id}/dokumen', [PengajuanController::class, 'downloadDokumen']);

    // Upload media untuk artikel (tersedia untuk mahasiswa & petugas)
    Route::post('/posts/upload-image', [PostController::class, 'uploadImage']);

    // Mahasiswa ─────────────────────────────────────────────────────────
    Route::middleware('role:mahasiswa')->group(function () {
        Route::get('/pengajuan/saya', [PengajuanController::class, 'myPengajuan']);
        Route::post('/pengajuan', [PengajuanController::class, 'store']);
        Route::get('/pengajuan/{id}', [PengajuanController::class, 'show']);
        Route::post('/pengajuan/{id}/revisi', [PengajuanController::class, 'submitRevision']);

        // Artikel Mahasiswa (Kontributor)
        Route::get('/my/posts', [PostController::class, 'myPosts']);
        Route::get('/my/posts/{id}', [PostController::class, 'myPostShow']);
        Route::post('/posts', [PostController::class, 'store']);
        Route::patch('/my/posts/{id}', [PostController::class, 'myPostUpdate']);
        Route::post('/my/posts/{id}/submit', [PostController::class, 'myPostSubmit']);
    });

    // Petugas ───────────────────────────────────────────────────────────
    Route::middleware('role:petugas')->group(function () {
        Route::get('/pengajuan', [PengajuanController::class, 'index']);
        Route::get('/admin/pengajuan/statistics', [PengajuanController::class, 'statistics']);
        Route::get('/admin/pengajuan/export', [PengajuanController::class, 'export']);
        Route::get('/admin/pengajuan/{id}', [PengajuanController::class, 'show']);
        Route::patch('/pengajuan/{id}/status', [PengajuanController::class, 'updateStatus']);
        Route::post('/pengajuan/{id}/minta-revisi', [PengajuanController::class, 'requestRevision']);
        Route::get('/admin/activity-logs', [ActivityLogController::class, 'index']);

        // Layanan CRUD (hanya petugas)
        Route::post('/layanan', [LayananController::class, 'store']);
        Route::put('/layanan/{layanan}', [LayananController::class, 'update']);
        Route::delete('/layanan/{layanan}', [LayananController::class, 'destroy']);

        // Moderasi & CMS Pengumuman Blog Petugas
        Route::get('/admin/posts', [AdminPostController::class, 'index']);
        Route::get('/admin/posts/pending', [AdminPostController::class, 'pending']);
        Route::get('/admin/posts/{id}', [AdminPostController::class, 'show']);
        Route::post('/admin/posts', [AdminPostController::class, 'store']);
        Route::patch('/admin/posts/{id}', [AdminPostController::class, 'update']);
        Route::post('/admin/posts/{id}/publish', [AdminPostController::class, 'publish']);
        Route::post('/admin/posts/{id}/approve', [AdminPostController::class, 'approve']);
        Route::post('/admin/posts/{id}/reject', [AdminPostController::class, 'reject']);
        Route::post('/admin/posts/{id}/archive', [AdminPostController::class, 'archive']);
        Route::get('/admin/posts/{id}/reviews', [AdminPostController::class, 'reviews']);
    });
});
