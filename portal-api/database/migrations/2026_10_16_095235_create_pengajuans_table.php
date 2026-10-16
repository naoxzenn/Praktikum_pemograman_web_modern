<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuans', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pengajuan')->unique();
            $table->foreignId('layanan_id')
                ->constrained('layanans')
                ->restrictOnDelete();
            $table->string('nama_mahasiswa', 150);
            $table->string('nim', 30);
            $table->string('email');
            $table->string('status')->default('Menunggu');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuans');
    }
};
