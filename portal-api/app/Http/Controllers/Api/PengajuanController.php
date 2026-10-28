<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Layanan;
use App\Models\Notification;
use App\Models\Pengajuan;
use App\Models\PengajuanHistory;
use App\Models\Post;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PengajuanController extends Controller
{
    /**
     * Daftar pengajuan untuk Petugas.
     * Mendukung pencarian, filter status, filter layanan, dan paginasi backend.
     */
    public function index(Request $request)
    {
        $query = Pengajuan::with(['layanan', 'user', 'diprosesOleh'])->latest();

        // Filter pencarian: nama mahasiswa, nim, nomor pengajuan, atau nama layanan
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nama_mahasiswa', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%")
                  ->orWhere('nomor_pengajuan', 'like', "%{$search}%")
                  ->orWhereHas('layanan', function ($lq) use ($search) {
                      $lq->where('nama', 'like', "%{$search}%");
                  });
            });
        }

        // Filter status
        if ($request->filled('status') && $request->input('status') !== 'Semua') {
            $query->where('status', $request->input('status'));
        }

        // Filter layanan
        if ($request->filled('layanan_id') && is_numeric($request->input('layanan_id'))) {
            $query->where('layanan_id', (int) $request->input('layanan_id'));
        }

        // Jika request meminta paginasi atau membawa parameter halaman
        if ($request->has('page') || $request->has('per_page') || $request->boolean('paginate')) {
            $perPage = min(max((int) $request->input('per_page', 10), 1), 50);
            return response()->json($query->paginate($perPage));
        }

        // Kompatibilitas dengan pemanggil list biasa
        return response()->json($query->get());
    }

    /**
     * Mahasiswa membuat pengajuan baru, dengan upload dokumen aman & estimasi SLA.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'layanan_id'     => ['required', 'integer', 'exists:layanans,id'],
            'nama_mahasiswa' => ['required', 'string', 'max:150'],
            'nim'            => ['required', 'string', 'max:30'],
            'email'          => ['required', 'email', 'max:255'],
            'keterangan'     => ['nullable', 'string', 'max:1000'],
            'dokumen'        => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // Max 5MB
        ]);

        $layanan = Layanan::findOrFail($data['layanan_id']);

        // Tangani upload dokumen jika ada
        $dokumenPath = null;
        $dokumenNamaAsli = null;
        $dokumenMime = null;

        if ($request->hasFile('dokumen')) {
            $file = $request->file('dokumen');
            // Simpan di private storage (tidak bisa diakses langsung via URL publik)
            $dokumenPath = $file->store('private/dokumen_pengajuan');
            $dokumenNamaAsli = $file->getClientOriginalName();
            $dokumenMime = $file->getClientMimeType() ?: $file->getMimeType();
        }

        // Hitung estimasi selesai berdasarkan SLA hari kerja
        $slaHariKerja = $layanan->sla_hari_kerja ?? 3;
        $estimasiSelesai = Pengajuan::hitungEstimasiSla(now(), $slaHariKerja);

        $pengajuan = DB::transaction(function () use ($request, $data, $dokumenPath, $dokumenNamaAsli, $dokumenMime, $estimasiSelesai) {
            $nomor = Pengajuan::generateUniqueNomor();

            $p = Pengajuan::create([
                'user_id'           => $request->user()->id,
                'layanan_id'        => $data['layanan_id'],
                'nomor_pengajuan'   => $nomor,
                'nama_mahasiswa'    => $data['nama_mahasiswa'],
                'nim'               => $data['nim'],
                'email'             => $data['email'],
                'keterangan'        => $data['keterangan'] ?? null,
                'dokumen_path'      => $dokumenPath,
                'dokumen_nama_asli' => $dokumenNamaAsli,
                'dokumen_mime'      => $dokumenMime,
                'status'            => 'Menunggu',
                'estimasi_selesai'  => $estimasiSelesai,
            ]);

            // Catat riwayat awal di timeline
            PengajuanHistory::create([
                'pengajuan_id'      => $p->id,
                'user_id'           => $request->user()->id,
                'status_sebelumnya' => null,
                'status_baru'       => 'Menunggu',
                'catatan'           => 'Pengajuan dibuat oleh mahasiswa.',
                'created_at'        => now(),
            ]);

            // Catat log aktivitas
            ActivityLog::record(
                $request->user(),
                'pengajuan_create',
                "Membuat pengajuan layanan {$p->nomor_pengajuan}",
                $p,
                ['nomor_pengajuan' => $p->nomor_pengajuan],
                $request->ip()
            );

            // Beri notifikasi ke semua petugas bahwa ada pengajuan baru masuk
            $petugasList = User::where('role', 'petugas')->get();
            foreach ($petugasList as $petugas) {
                Notification::createNotification(
                    $petugas->id,
                    'pengajuan_masuk',
                    'Pengajuan Layanan Baru',
                    "Pengajuan {$p->nomor_pengajuan} dari {$p->nama_mahasiswa} telah masuk.",
                    ['nomor_pengajuan' => $p->nomor_pengajuan, 'id' => $p->id]
                );
            }

            return $p;
        });

        return response()->json([
            'message' => 'Pengajuan berhasil dikirim.',
            'data'    => $pengajuan->load(['layanan', 'histories']),
        ], 201);
    }

    /**
     * Detail pengajuan dengan timeline riwayat status.
     * Mahasiswa hanya boleh melihat pengajuan miliknya, petugas boleh melihat semua.
     */
    public function show(Request $request, string $id)
    {
        $query = Pengajuan::with([
            'layanan',
            'diprosesOleh',
            'histories' => fn($q) => $q->with('user:id,name,role')->orderBy('created_at', 'asc'),
        ]);

        if (is_numeric($id)) {
            $query->where('id', (int) $id);
        } else {
            $query->where('nomor_pengajuan', $id);
        }

        // Otorisasi kepemilikan jika mahasiswa
        if ($request->user()->role === 'mahasiswa') {
            $query->where('user_id', $request->user()->id);
        }

        $pengajuan = $query->firstOrFail();

        return response()->json($pengajuan);
    }

    /**
     * Petugas memperbarui status pengajuan.
     */
    public function updateStatus(Request $request, string $id)
    {
        $data = $request->validate([
            'status'  => ['required', 'string', 'in:Menunggu,Diproses,Selesai,Ditolak'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $query = Pengajuan::query();
        if (is_numeric($id)) {
            $query->where('id', (int) $id);
        } else {
            $query->where('nomor_pengajuan', $id);
        }
        $pengajuan = $query->firstOrFail();

        $statusLama = $pengajuan->status;
        $statusBaru = $data['status'];

        DB::transaction(function () use ($request, $pengajuan, $statusLama, $statusBaru, $data) {
            $pengajuan->update([
                'status'        => $statusBaru,
                'diproses_oleh' => $request->user()->id,
            ]);

            // Catat ke timeline
            PengajuanHistory::create([
                'pengajuan_id'      => $pengajuan->id,
                'user_id'           => $request->user()->id,
                'status_sebelumnya' => $statusLama,
                'status_baru'       => $statusBaru,
                'catatan'           => $data['catatan'] ?? "Status diubah menjadi {$statusBaru}.",
                'created_at'        => now(),
            ]);

            // Catat log aktivitas
            ActivityLog::record(
                $request->user(),
                'status_update',
                "Mengubah status {$pengajuan->nomor_pengajuan} dari {$statusLama} ke {$statusBaru}",
                $pengajuan,
                [
                    'nomor_pengajuan' => $pengajuan->nomor_pengajuan,
                    'status_sebelum'  => $statusLama,
                    'status_baru'     => $statusBaru,
                    'catatan'         => $data['catatan'] ?? null,
                ],
                $request->ip()
            );

            // Kirim notifikasi ke mahasiswa pemilik
            if ($pengajuan->user_id) {
                Notification::createNotification(
                    $pengajuan->user_id,
                    'pengajuan_status',
                    'Pembaruan Status Pengajuan',
                    "Pengajuan {$pengajuan->nomor_pengajuan} telah diperbarui menjadi '{$statusBaru}'.",
                    [
                        'nomor_pengajuan' => $pengajuan->nomor_pengajuan,
                        'status'          => $statusBaru,
                        'catatan'         => $data['catatan'] ?? null,
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Status pengajuan berhasil diperbarui.',
            'data'    => $pengajuan->fresh()->load(['layanan', 'diprosesOleh', 'histories']),
        ]);
    }

    /**
     * Petugas meminta perbaikan dokumen (alasan revisi wajib diisi).
     */
    public function requestRevision(Request $request, string $id)
    {
        $data = $request->validate([
            'catatan_revisi' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $query = Pengajuan::query();
        if (is_numeric($id)) {
            $query->where('id', (int) $id);
        } else {
            $query->where('nomor_pengajuan', $id);
        }
        $pengajuan = $query->firstOrFail();

        $statusLama = $pengajuan->status;

        DB::transaction(function () use ($request, $pengajuan, $statusLama, $data) {
            $pengajuan->update([
                'status'         => 'Perlu Perbaikan',
                'catatan_revisi' => $data['catatan_revisi'],
                'diproses_oleh'  => $request->user()->id,
            ]);

            PengajuanHistory::create([
                'pengajuan_id'      => $pengajuan->id,
                'user_id'           => $request->user()->id,
                'status_sebelumnya' => $statusLama,
                'status_baru'       => 'Perlu Perbaikan',
                'catatan'           => 'Permintaan perbaikan: ' . $data['catatan_revisi'],
                'created_at'        => now(),
            ]);

            ActivityLog::record(
                $request->user(),
                'revisi_request',
                "Meminta perbaikan dokumen untuk {$pengajuan->nomor_pengajuan}",
                $pengajuan,
                ['alasan' => $data['catatan_revisi']],
                $request->ip()
            );

            if ($pengajuan->user_id) {
                Notification::createNotification(
                    $pengajuan->user_id,
                    'revisi_dokumen',
                    'Permintaan Perbaikan Dokumen',
                    "Petugas meminta perbaikan dokumen pada {$pengajuan->nomor_pengajuan}: {$data['catatan_revisi']}",
                    [
                        'nomor_pengajuan' => $pengajuan->nomor_pengajuan,
                        'catatan_revisi'  => $data['catatan_revisi'],
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Permintaan perbaikan dokumen berhasil dikirim ke mahasiswa.',
            'data'    => $pengajuan->fresh()->load(['layanan', 'diprosesOleh', 'histories']),
        ]);
    }

    /**
     * Mahasiswa mengunggah dokumen revisi pengganti.
     */
    public function submitRevision(Request $request, string $id)
    {
        $data = $request->validate([
            'dokumen'    => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $query = Pengajuan::where('user_id', $request->user()->id);
        if (is_numeric($id)) {
            $query->where('id', (int) $id);
        } else {
            $query->where('nomor_pengajuan', $id);
        }
        $pengajuan = $query->firstOrFail();

        if ($pengajuan->status !== 'Perlu Perbaikan') {
            return response()->json([
                'message' => 'Pengajuan ini tidak sedang dalam status Perlu Perbaikan.',
            ], 422);
        }

        $file = $request->file('dokumen');
        $dokumenPath = $file->store('private/dokumen_pengajuan');
        $dokumenNamaAsli = $file->getClientOriginalName();
        $dokumenMime = $file->getClientMimeType() ?: $file->getMimeType();

        DB::transaction(function () use ($request, $pengajuan, $data, $dokumenPath, $dokumenNamaAsli, $dokumenMime) {
            $catatanTambahan = $data['keterangan'] ?? 'Dokumen perbaikan telah diunggah oleh mahasiswa.';

            $pengajuan->update([
                'status'            => 'Menunggu',
                'dokumen_path'      => $dokumenPath,
                'dokumen_nama_asli' => $dokumenNamaAsli,
                'dokumen_mime'      => $dokumenMime,
                'keterangan'        => $data['keterangan'] ?: $pengajuan->keterangan,
            ]);

            PengajuanHistory::create([
                'pengajuan_id'      => $pengajuan->id,
                'user_id'           => $request->user()->id,
                'status_sebelumnya' => 'Perlu Perbaikan',
                'status_baru'       => 'Menunggu',
                'catatan'           => $catatanTambahan,
                'created_at'        => now(),
            ]);

            ActivityLog::record(
                $request->user(),
                'revisi_submit',
                "Mengunggah perbaikan dokumen untuk {$pengajuan->nomor_pengajuan}",
                $pengajuan,
                ['dokumen_asli' => $dokumenNamaAsli],
                $request->ip()
            );

            // Beri tahu petugas
            $petugasList = User::where('role', 'petugas')->get();
            foreach ($petugasList as $petugas) {
                Notification::createNotification(
                    $petugas->id,
                    'revisi_selesai',
                    'Revisi Dokumen Diunggah',
                    "Mahasiswa telah mengunggah revisi dokumen untuk {$pengajuan->nomor_pengajuan}.",
                    ['nomor_pengajuan' => $pengajuan->nomor_pengajuan, 'id' => $pengajuan->id]
                );
            }
        });

        return response()->json([
            'message' => 'Dokumen perbaikan berhasil dikirim.',
            'data'    => $pengajuan->fresh()->load(['layanan', 'diprosesOleh', 'histories']),
        ]);
    }

    /**
     * Unduh dokumen lampiran secara aman.
     * Hanya pemilik (mahasiswa) atau petugas yang dapat mengunduh.
     */
    public function downloadDokumen(Request $request, string $id)
    {
        $query = Pengajuan::query();
        if (is_numeric($id)) {
            $query->where('id', (int) $id);
        } else {
            $query->where('nomor_pengajuan', $id);
        }

        // Jika mahasiswa, batasi ke miliknya sendiri
        if ($request->user()->role === 'mahasiswa') {
            $query->where('user_id', $request->user()->id);
        }

        $pengajuan = $query->firstOrFail();

        if (!$pengajuan->dokumen_path) {
            return response()->json(['message' => 'Dokumen tidak tersedia pada pengajuan ini.'], 404);
        }

        // Sanitasi path traversal
        $path = $pengajuan->dokumen_path;
        if (str_contains($path, '..')) {
            return response()->json(['message' => 'Jalur file tidak valid.'], 400);
        }

        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'File fisik tidak ditemukan pada server.'], 404);
        }

        $namaUnduhan = $pengajuan->dokumen_nama_asli ?: basename($path);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return $disk->download($path, $namaUnduhan, [
            'Content-Type' => $pengajuan->dokumen_mime ?: 'application/octet-stream',
        ]);
    }

    /**
     * Statistik dashboard petugas dari database aktual.
     */
    public function statistics(Request $request)
    {
        $now = now()->toDateString();

        $total = Pengajuan::count();
        $menunggu = Pengajuan::where('status', 'Menunggu')->count();
        $diproses = Pengajuan::where('status', 'Diproses')->count();
        $perluPerbaikan = Pengajuan::where('status', 'Perlu Perbaikan')->count();
        $selesai = Pengajuan::where('status', 'Selesai')->count();
        $ditolak = Pengajuan::where('status', 'Ditolak')->count();

        // Pengajuan terlambat: status aktif (Menunggu/Diproses/Perlu Perbaikan) yang estimasi_selesai < hari ini
        $terlambat = Pengajuan::whereIn('status', ['Menunggu', 'Diproses', 'Perlu Perbaikan'])
            ->whereNotNull('estimasi_selesai')
            ->whereDate('estimasi_selesai', '<', $now)
            ->count();

        // Pengajuan hari ini
        $hariIni = Pengajuan::whereDate('created_at', $now)->count();

        // Blog pending moderasi
        $blogPending = Post::where('status', 'pending')->count();
        $blogPublished = Post::where('status', 'published')->count();

        return response()->json([
            'pengajuan' => [
                'total'           => $total,
                'menunggu'        => $menunggu,
                'diproses'        => $diproses,
                'perlu_perbaikan' => $perluPerbaikan,
                'selesai'         => $selesai,
                'ditolak'         => $ditolak,
                'terlambat'       => $terlambat,
                'hari_ini'        => $hariIni,
            ],
            'blog' => [
                'pending'   => $blogPending,
                'published' => $blogPublished,
            ],
        ]);
    }

    /**
     * Ekspor laporan pengajuan ke CSV yang aman dari Formula Injection.
     * Hanya dapat diakses oleh petugas.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Pengajuan::with(['layanan', 'diprosesOleh'])->latest();

        if ($request->filled('status') && $request->input('status') !== 'Semua') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('layanan_id') && is_numeric($request->input('layanan_id'))) {
            $query->where('layanan_id', (int) $request->input('layanan_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        ActivityLog::record(
            $request->user(),
            'export_report',
            'Mengekspor laporan pengajuan layanan ke CSV',
            null,
            $request->only(['status', 'layanan_id', 'start_date', 'end_date']),
            $request->ip()
        );

        $filename = 'laporan_pengajuan_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Tulis BOM UTF-8 agar kompatibel dengan Microsoft Excel
            fputs($handle, "\xEF\xBB\xBF");

            // Header kolom
            fputcsv($handle, [
                'No.',
                'Nomor Pengajuan',
                'Tanggal Masuk',
                'NIM',
                'Nama Mahasiswa',
                'Email',
                'Jenis Layanan',
                'Status',
                'Estimasi Selesai',
                'Keterangan',
                'Petugas Pemroses',
            ]);

            $counter = 1;

            // Chunking per 200 baris untuk efisiensi memori
            $query->chunk(200, function ($items) use ($handle, &$counter) {
                foreach ($items as $item) {
                    fputcsv($handle, [
                        $counter++,
                        self::sanitizeCsv($item->nomor_pengajuan),
                        $item->created_at ? $item->created_at->format('Y-m-d H:i') : '',
                        self::sanitizeCsv($item->nim),
                        self::sanitizeCsv($item->nama_mahasiswa),
                        self::sanitizeCsv($item->email),
                        self::sanitizeCsv($item->layanan?->nama ?? '-'),
                        self::sanitizeCsv($item->status),
                        $item->estimasi_selesai ? $item->estimasi_selesai->format('Y-m-d') : '-',
                        self::sanitizeCsv($item->keterangan ?? '-'),
                        self::sanitizeCsv($item->diprosesOleh?->name ?? '-'),
                    ]);
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Sanitasi sel CSV untuk mencegah CSV Formula Injection (Excel DDE/Macro Injection).
     * Jika karakter pertama adalah =, +, -, @, \t, atau \r, berikan prefix kutip tunggal (').
     */
    private static function sanitizeCsv(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $firstChar = substr($value, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Daftar pengajuan milik mahasiswa yang sedang login.
     */
    public function myPengajuan(Request $request)
    {
        return response()->json(
            Pengajuan::with(['layanan', 'diprosesOleh'])
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get()
        );
    }
}
