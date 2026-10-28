<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use App\Services\ContentSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostController extends Controller
{
    // ── Publik ─────────────────────────────────────────────────────────────

    /**
     * Menampilkan daftar artikel yang sudah dipublikasikan (published).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::with([
            'author:id,name',
            'category:id,name,slug,badge_color'
        ])
        ->where('status', 'published');

        // Filter kategori (berdasarkan slug atau id)
        if ($categorySlug = $request->query('category')) {
            if ($categorySlug !== 'Semua') {
                $query->whereHas('category', function ($q) use ($categorySlug) {
                    $q->where('slug', $categorySlug)->orWhere('name', $categorySlug);
                });
            }
        }

        // Pencarian judul atau ringkasan
        if ($search = trim($request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->query('per_page', 9), 50);
        $posts = $query->orderBy('published_at', 'desc')->paginate($perPage);

        return response()->json($posts);
    }

    /**
     * Menampilkan detail artikel publik berdasarkan slug.
     */
    public function show(string $slug): JsonResponse
    {
        $post = Post::with([
            'author:id,name',
            'category:id,name,slug,badge_color'
        ])
        ->where('slug', $slug)
        ->where('status', 'published')
        ->first();

        if (!$post) {
            return response()->json([
                'message' => 'Artikel tidak ditemukan atau belum dipublikasikan.'
            ], 404);
        }

        return response()->json($post);
    }

    // ── Upload Media ────────────────────────────────────────────────────────

    /**
     * Mengunggah gambar untuk cover atau isi artikel (editor Tiptap).
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'image.required' => 'File gambar wajib disertakan.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format gambar yang didukung: JPEG, PNG, JPG, WEBP.',
            'image.max' => 'Ukuran gambar maksimal adalah 2MB.',
        ]);

        $file = $request->file('image');
        $filename = 'post_' . Str::random(24) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('posts', $filename, 'public');

        return response()->json([
            'url' => asset('storage/' . $path),
            'path' => $path,
            'message' => 'Gambar berhasil diunggah.'
        ], 201);
    }

    // ── Mahasiswa (Artikel Saya) ───────────────────────────────────────────

    /**
     * Menampilkan daftar artikel milik mahasiswa yang sedang login.
     */
    public function myPosts(Request $request): JsonResponse
    {
        $user = $request->user();

        $posts = Post::with([
            'category:id,name,slug,badge_color',
            'reviewer:id,name'
        ])
        ->where('user_id', $user->id)
        ->latest('updated_at')
        ->get();

        return response()->json($posts);
    }

    /**
     * Menampilkan detail artikel milik mahasiswa yang sedang login.
     */
    public function myPostShow(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $post = Post::with([
            'category:id,name,slug,badge_color',
            'reviews.reviewer:id,name'
        ])
        ->where('user_id', $user->id)
        ->where('id', $id)
        ->first();

        if (!$post) {
            return response()->json([
                'message' => 'Artikel tidak ditemukan atau bukan milik Anda.'
            ], 404);
        }

        return response()->json($post);
    }

    /**
     * Membuat artikel baru oleh mahasiswa.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:post_categories,id',
            'summary' => 'required|string|max:600',
            'content' => 'required|string',
            'cover_image_path' => 'nullable|string|max:500',
            'action' => 'nullable|in:draft,submit',
        ]);

        // Mahasiswa tidak diizinkan mengatur status published secara langsung
        $action = $validated['action'] ?? 'draft';
        $status = ($action === 'submit') ? 'pending' : 'draft';

        $slug = Post::generateUniqueSlug($validated['title']);
        $sanitizedContent = ContentSanitizer::clean($validated['content']);

        $post = Post::create([
            'user_id' => $user->id,
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'slug' => $slug,
            'summary' => $validated['summary'],
            'content' => $sanitizedContent,
            'cover_image_path' => $validated['cover_image_path'] ?? null,
            'status' => $status,
        ]);

        return response()->json([
            'message' => $status === 'pending'
                ? 'Artikel berhasil dikirim untuk ditinjau oleh petugas.'
                : 'Draf artikel berhasil disimpan.',
            'post' => $post->load('category:id,name,slug,badge_color')
        ], 201);
    }

    /**
     * Mengedit draft atau artikel rejected milik mahasiswa.
     */
    public function myPostUpdate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $post = Post::where('user_id', $user->id)->where('id', $id)->first();

        if (!$post) {
            return response()->json([
                'message' => 'Artikel tidak ditemukan.'
            ], 404);
        }

        if ($post->status === 'published') {
            return response()->json([
                'message' => 'Artikel yang sudah diterbitkan tidak dapat diubah tanpa proses editorial.'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:post_categories,id',
            'summary' => 'required|string|max:600',
            'content' => 'required|string',
            'cover_image_path' => 'nullable|string|max:500',
            'action' => 'nullable|in:draft,submit',
        ]);

        // Perbarui slug jika judul berubah
        if ($post->title !== $validated['title']) {
            $post->slug = Post::generateUniqueSlug($validated['title'], $post->id);
        }

        $post->title = $validated['title'];
        $post->category_id = $validated['category_id'];
        $post->summary = $validated['summary'];
        $post->content = ContentSanitizer::clean($validated['content']);
        $post->cover_image_path = $validated['cover_image_path'] ?? $post->cover_image_path;

        $action = $validated['action'] ?? null;
        if ($action === 'submit') {
            $post->status = 'pending';
            // Catatan penolakan diarsipkan di riwayat reviews, status sekarang pending
            $post->rejection_reason = null;
        }

        $post->save();

        return response()->json([
            'message' => $post->status === 'pending'
                ? 'Artikel berhasil dikirim ulang untuk ditinjau petugas.'
                : 'Perubahan artikel berhasil disimpan.',
            'post' => $post->fresh(['category:id,name,slug,badge_color'])
        ]);
    }

    /**
     * Mengirim artikel draft atau rejected untuk review petugas.
     */
    public function myPostSubmit(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $post = Post::where('user_id', $user->id)->where('id', $id)->first();

        if (!$post) {
            return response()->json([
                'message' => 'Artikel tidak ditemukan.'
            ], 404);
        }

        if ($post->status === 'published') {
            return response()->json([
                'message' => 'Artikel ini sudah diterbitkan.'
            ], 400);
        }

        if ($post->status === 'pending') {
            return response()->json([
                'message' => 'Artikel ini sudah dalam antrean review.'
            ], 400);
        }

        $post->status = 'pending';
        $post->rejection_reason = null;
        $post->save();

        return response()->json([
            'message' => 'Artikel berhasil dikirim untuk ditinjau oleh petugas.',
            'post' => $post
        ]);
    }
}

