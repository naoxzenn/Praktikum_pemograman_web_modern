<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengajuan extends Model
{
    protected $fillable = [
        'nomor_pengajuan',
        'layanan_id',
        'nama_mahasiswa',
        'nim',
        'email',
        'status',
    ];

    public function layanan(): BelongsTo
    {
        return $this->belongsTo(Layanan::class);
    }
}
