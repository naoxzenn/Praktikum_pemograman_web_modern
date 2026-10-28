<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom SLA ke tabel layanans.
     * Migration AMAN: hanya menambah kolom nullable dengan default 3.
     * Data lama tidak terpengaruh.
     */
    public function up(): void
    {
        Schema::table('layanans', function (Blueprint $table) {
            // Estimasi penyelesaian dalam hari kerja (bukan hari kalender)
            $table->unsignedTinyInteger('sla_hari_kerja')->default(3)->after('deskripsi');
        });
    }

    public function down(): void
    {
        Schema::table('layanans', function (Blueprint $table) {
            $table->dropColumn('sla_hari_kerja');
        });
    }
};

