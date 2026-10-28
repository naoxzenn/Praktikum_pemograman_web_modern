<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Daftar log aktivitas sistem (hanya dapat diakses oleh petugas).
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user:id,name,email,role')->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        return response()->json($query->paginate($perPage));
    }
}

