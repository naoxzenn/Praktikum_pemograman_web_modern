<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostReview;
use App\Services\ContentSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPostController extends Controller
{
    /**
     * Menampilkan daftar semua artikel untuk admin dengan filter status, kategori, dan pencarian.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::with([
            'author:id,name,role',
            'category:id,name,slug,badge_color',
            'reviewer:id,name'
        ]);

        if ($status = $request->query('status')) {
            if ($status !== 'all' && $status !== 'Semua') {
                $query->where('status', $status);
            }
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($search = trim($request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhereHas('author', fn($aq) => $aq->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = min((int) $request->query('per_page', 15), 50);
        $posts = $query->latest('updated_at')->paginate($perPage);

        return response()->json($posts);
    }

    /**
     * Menampilkan antrean artikel mahasiswa yang berstatus pending (menunggu review).
     */
    public function pending(): JsonResponse
    {
        $posts = Post::with([
            'author:id,name',
            'category:id,name,slug,badge_color',
            'reviews.reviewer:id,name'
        ])
        ->where('status', 'pending')
        ->oldest('created_at')
        ->get();

        return response()->json($posts);
    }

    /**
     * Menampilkan detail artikel untuk pengelolaan admin.
     */
    public function show(int $id): JsonResponse
    {
        $post = Post::with([
            'author:id,name,role',
            'category:id,name,slug,badge_color',
            'reviewer:id,name',
            'reviews.reviewer:id,name'
        ])->find($id);

        if (!$post) {
            return response()->json([
                'message' => 'Artikel tidak ditemukan.'
            ], 404);
        }

        return response()->json($post);
    }

    /**
     * Petugas membuat artikel / pengumuman resmi kampus.
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
            'action' => 'nullable|in:draft,publish',
        ]);

        $action = $validated['action'] ?? 'draft';
        $isPublish = ($action === 'publish');

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
            'status' => $isPublish ? 'published' : 'draft',
            'published_at' => $isPublish ? now() : null,
            'reviewed_by' => $isPublish ? $user->id : null,
        ]);

        return response()->json([
            'message' => $isPublish
                ? 'Pengumuman resmi berhasil diterbitkan secara langsung.'
                : 'Draf pengumuman resmi berhasil disimpan.',
            'post' => $post->load('category:id,name,slug,badge_color')
        ], 201);
    }

    /**
     * Petugas memperbarui artikel.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json([
                'message' => 'Artikel tidak ditemukan.'
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:post_categories,id',
            'summary' => 'required|string|max:600',
            'content' => 'required|string',
            'cover_image_path' => 'nullable|string|max:500',
            'status' => 'nullable|in:draft,pending,published,rejected',
        ]);

        if ($post->title !== $validated['title']) {
            $post->slug = Post::generateUniqueSlug($validated['title'], $post->id);
        }

        $post->title = $validated['title'];
        $post->category_id = $validated['category_id'];
        $post->summary = $validated['summary'];
        $post->content = ContentSanitizer::clean($validated['content']);
        $post->cover_image_path = $validated['cover_image_path'] ?? $post->cover_image_path;

        if (!empty($validated['status'])) {
            $post->status = $validated['status'];
            if ($validated['status'] === 'published' && !$post->published_at) {
                $post->published_at = now();
            }
        }

        $post->save();

        return response()->json([
            'message' => 'Artikel berhasil diperbarui.',
            'post' => $post->fresh(['category:id,name,slug,badge_color', 'author:id,name'])
        ]);
    }

    /**
     * Petugas menyetujui artikel mahasiswa.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($id, $user, $request) {
            $post = Post::lockForUpdate()->find($id);

            if (!$post) {
                return response()->json([
                    'message' => 'Artikel tidak ditemukan.'
                ], 404);
            }

            if ($post->status !== 'pending') {
                return response()->json([
                    'message' => "Hanya artikel berstatus 'pending' yang dapat disetujui (status saat ini: {$post->status})."
                ], 422);
            }

            $post->status = 'published';
            $post->reviewed_by = $user->id;
            $post->rejection_reason = null;
            if (!$post->published_at) {
                $post->published_at = now();
            }
            $post->save();

            PostReview::create([
                'post_id' => $post->id,
                'reviewer_id' => $user->id,
                'decision' => 'approved',
                'notes' => $request->input('notes', 'Artikel disetujui untuk publikasi.'),
            ]);

            ActivityLog::record(
                $user,
                'post_approve',
                "Menyetujui artikel '{$post->title}' untuk publikasi",
                $post,
                ['post_id' => $post->id, 'title' => $post->title],
                $request->ip()
            );

            if ($post->user_id) {
                Notification::createNotification(
                    $post->user_id,
                    'blog_review',
                    'Artikel Disetujui',
                    "Artikel Anda '{$post->title}' telah disetujui dan diterbitkan.",
                    ['post_id' => $post->id, 'slug' => $post->slug]
                );
            }

            return response()->json([
                'message' => 'Artikel berhasil disetujui dan dipublikasikan.',
                'post' => $post->fresh(['category:id,name,slug,badge_color', 'author:id,name', 'reviews.reviewer:id,name'])
            ]);
        });
    }

    /**
     * Petugas menolak artikel mahasiswa dengan alasan wajib.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi agar mahasiswa dapat melakukan revisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        return DB::transaction(function () use ($id, $user, $validated, $request) {
            $post = Post::lockForUpdate()->find($id);

            if (!$post) {
                return response()->json([
                    'message' => 'Artikel tidak ditemukan.'
                ], 404);
            }

            if ($post->status !== 'pending') {
                return response()->json([
                    'message' => "Hanya artikel berstatus 'pending' yang dapat ditolak (status saat ini: {$post->status})."
                ], 422);
            }

            $reason = trim($validated['reason']);

            $post->status = 'rejected';
            $post->reviewed_by = $user->id;
            $post->rejection_reason = $reason;
            $post->save();

            PostReview::create([
                'post_id' => $post->id,
                'reviewer_id' => $user->id,
                'decision' => 'rejected',
                'notes' => $reason,
            ]);

            ActivityLog::record(
                $user,
                'post_reject',
                "Menolak artikel '{$post->title}' dengan alasan: {$reason}",
                $post,
                ['post_id' => $post->id, 'reason' => $reason],
                $request->ip()
            );

            if ($post->user_id) {
                Notification::createNotification(
                    $post->user_id,
                    'blog_review',
                    'Artikel Perlu Perbaikan',
                    "Artikel Anda '{$post->title}' ditolak oleh moderator. Alasan: {$reason}",
                    ['post_id' => $post->id, 'reason' => $reason]
                );
            }

            return response()->json([
                'message' => 'Artikel berhasil ditolak. Catatan perbaikan telah dikirim ke penulis.',
                'post' => $post->fresh(['category:id,name,slug,badge_color', 'author:id,name', 'reviews.reviewer:id,name'])
            ]);
        });
    }

    /**
     * Memublikasikan pengumuman atau draft langsung.
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $post = Post::find($id);

        if (!$post) {
            return response()->json(['message' => 'Artikel tidak ditemukan.'], 404);
        }

        $post->status = 'published';
        $post->reviewed_by = $user->id;
        if (!$post->published_at) {
            $post->published_at = now();
        }
        $post->save();

        return response()->json([
            'message' => 'Artikel berhasil dipublikasikan.',
            'post' => $post
        ]);
    }

    /**
     * Mengarsipkan artikel bermasalah atau menyembunyikan dari publik.
     */
    public function archive(Request $request, int $id): JsonResponse
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json(['message' => 'Artikel tidak ditemukan.'], 404);
        }

        $post->status = 'draft';
        $post->save();

        return response()->json([
            'message' => 'Artikel berhasil diarsipkan (status diubah menjadi draft).',
            'post' => $post
        ]);
    }

    /**
     * Riwayat moderasi artikel.
     */
    public function reviews(int $id): JsonResponse
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json(['message' => 'Artikel tidak ditemukan.'], 404);
        }

        $reviews = PostReview::with('reviewer:id,name')
            ->where('post_id', $id)
            ->latest()
            ->get();

        return response()->json($reviews);
    }
}

