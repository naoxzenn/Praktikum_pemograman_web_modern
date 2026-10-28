<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Layanan extends Model
{
    protected $fillable = [
        'nama',
        'deskripsi',
        'sla_hari_kerja',
    ];

    protected function casts(): array
    {
        return [
            'sla_hari_kerja' => 'integer',
        ];
    }
}
