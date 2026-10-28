<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel riwayat perubahan status pengajuan (Timeline).
     * Setiap perubahan status menjadi satu baris record permanen.
     * Tidak pernah di-update atau di-delete (audit trail).
     */
    public function up(): void
    {
        Schema::create('pengajuan_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')
                ->constrained('pengajuans')
                ->cascadeOnDelete();
            // Aktor yang melakukan perubahan (null = sistem)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status_sebelumnya')->nullable(); // null jika record pertama (dibuat)
            $table->string('status_baru');
            // Catatan opsional dari petugas (misal alasan penolakan, permintaan revisi)
            $table->text('catatan')->nullable();
            // Jangan simpan secret/token — hanya informasi aksi
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_histories');
    }
};

