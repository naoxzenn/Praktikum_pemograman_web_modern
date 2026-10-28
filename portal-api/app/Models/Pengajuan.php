<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengajuan extends Model
{
    protected $fillable = [
        'user_id',
        'nomor_pengajuan',
        'layanan_id',
        'nama_mahasiswa',
        'nim',
        'email',
        'keterangan',
        'dokumen_path',
        'dokumen_nama_asli',
        'dokumen_mime',
        'status',
        'catatan_revisi',
        'estimasi_selesai',
        'diproses_oleh',
    ];

    protected function casts(): array
    {
        return [
            'estimasi_selesai' => 'date',
        ];
    }

    public function layanan(): BelongsTo
    {
        return $this->belongsTo(Layanan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function histories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PengajuanHistory::class)->orderBy('created_at', 'asc');
    }

    /**
     * Hitung estimasi tanggal selesai berdasarkan SLA hari kerja (Senin - Jumat).
     */
    public static function hitungEstimasiSla(\Carbon\CarbonInterface $startDate, int $hariKerja): \Carbon\Carbon
    {
        $current = \Carbon\Carbon::parse($startDate)->copy();
        $added = 0;
        while ($added < $hariKerja) {
            $current->addDay();
            // Lewati Sabtu (6) dan Minggu (0)
            if (!$current->isWeekend()) {
                $added++;
            }
        }
        return $current;
    }

    /**
     * Generate nomor pengajuan unik dengan loop pencegah tabrakan (collision safe).
     */
    public static function generateUniqueNomor(): string
    {
        $maxAttempts = 10;
        $attempt = 0;
        do {
            $nomor = 'PM-' . now()->format('Ymd') . '-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6));
            $attempt++;
        } while (self::where('nomor_pengajuan', $nomor)->exists() && $attempt < $maxAttempts);

        return $nomor;
    }
}
