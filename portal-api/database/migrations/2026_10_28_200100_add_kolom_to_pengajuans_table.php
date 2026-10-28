<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom keterangan, dokumen, revisi, dan estimasi ke pengajuans.
     * Migration AMAN: semua kolom nullable atau dengan default — data lama utuh.
     * Status 'Perlu Perbaikan' ditangani di aplikasi level; enum kolom status
     * diubah ke string agar kompatibel dengan nilai lama 'Menunggu', 'Diproses', dll.
     */
    public function up(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            // Keterangan tambahan saat pengajuan
            $table->text('keterangan')->nullable()->after('email');
            // Path dokumen lampiran (PDF/gambar) — privat storage
            $table->string('dokumen_path')->nullable()->after('keterangan');
            // Nama file asli dokumen (untuk unduhan)
            $table->string('dokumen_nama_asli')->nullable()->after('dokumen_path');
            // MIME type dokumen untuk verifikasi
            $table->string('dokumen_mime')->nullable()->after('dokumen_nama_asli');
            // Catatan revisi dari petugas
            $table->text('catatan_revisi')->nullable()->after('dokumen_mime');
            // Estimasi selesai berdasarkan SLA layanan
            $table->date('estimasi_selesai')->nullable()->after('catatan_revisi');
            // Siapa petugas yang terakhir mengubah status
            $table->foreignId('diproses_oleh')
                ->nullable()
                ->after('estimasi_selesai')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pengajuans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diproses_oleh');
            $table->dropColumn([
                'keterangan',
                'dokumen_path',
                'dokumen_nama_asli',
                'dokumen_mime',
                'catatan_revisi',
                'estimasi_selesai',
            ]);
        });
    }
};

