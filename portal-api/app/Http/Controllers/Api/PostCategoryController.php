<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PostCategory;
use Illuminate\Http\JsonResponse;

class PostCategoryController extends Controller
{
    /**
     * Mengembalikan daftar semua kategori artikel publik.
     */
    public function index(): JsonResponse
    {
        $categories = PostCategory::withCount([
            'posts' => fn($query) => $query->where('status', 'published')
        ])->get();

        return response()->json($categories);
    }
}

