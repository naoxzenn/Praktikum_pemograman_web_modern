<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengajuan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PengajuanController extends Controller
{
    public function index()
    {
        return response()->json(
            Pengajuan::with('layanan')->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'layanan_id' => ['required', 'integer', 'exists:layanans,id'],
            'nama_mahasiswa' => ['required', 'string', 'max:150'],
            'nim' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $pengajuan = Pengajuan::create([
            ...$data,
            'nomor_pengajuan' => 'PM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
            'status' => 'Menunggu',
        ]);

        return response()->json([
            'message' => 'Pengajuan berhasil dikirim.',
            'data' => $pengajuan->load('layanan'),
        ], 201);
    }

    public function show(string $id)
    {
        $pengajuan = Pengajuan::with('layanan')
            ->where('nomor_pengajuan', $id)
            ->firstOrFail();

        return response()->json($pengajuan);
    }


    public function updateStatus(Request $request, string $id)
    {
        $data = $request->validate([
            'status' => [
                'required',
                'string',
                'in:Menunggu,Diproses,Selesai,Ditolak',
            ],
        ]);

        $pengajuan = Pengajuan::where('nomor_pengajuan', $id)
            ->firstOrFail();

        $pengajuan->update([
            'status' => $data['status'],
        ]);

        return response()->json([
            'message' => 'Status pengajuan berhasil diperbarui.',
            'data' => $pengajuan->load('layanan'),
        ]);
    }
}
