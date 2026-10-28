<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel log audit aktivitas penting pengguna dan petugas.
     * Tidak menyimpan password, token, atau informasi kredensial.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('role', 30)->nullable();
            $table->string('action', 100); // contoh: status_update, revisi_request, revisi_submit, post_approve, post_reject, export_report
            $table->string('subject_type', 100)->nullable(); // App\Models\Pengajuan, App\Models\Post, dll
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 255);
            $table->json('properties')->nullable(); // detail ringkas perubahan aman
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

