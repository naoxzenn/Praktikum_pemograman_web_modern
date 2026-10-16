<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Layanan;
use Illuminate\Http\Request;

class LayananController extends Controller
{
    public function index()
    {
        return response()->json(
            \App\Models\Layanan::latest()->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        return response()->json(Layanan::create($data), 201);
    }

    public function show(Layanan $layanan)
    {
        return response()->json($layanan);
    }

    public function update(Request $request, Layanan $layanan)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $layanan->update($data);

        return response()->json($layanan);
    }

    public function destroy(Layanan $layanan)
    {
        $layanan->delete();

        return response()->noContent();
    }
}
