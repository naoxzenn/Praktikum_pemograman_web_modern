<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel notifikasi internal sistem.
     * Digunakan untuk pemberitahuan status pengajuan, revisi dokumen,
     * serta moderasi artikel blog.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('type', 100); // contoh: pengajuan_status, revisi_dokumen, blog_review, sistem
            $table->string('title', 200);
            $table->text('message');
            $table->json('data')->nullable(); // metadata referensi seperti nomor_pengajuan, post_slug, url
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

