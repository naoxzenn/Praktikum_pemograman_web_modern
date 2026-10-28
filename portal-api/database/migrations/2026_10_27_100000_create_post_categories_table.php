<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('badge_color')->default('#1e5948');
            $table->timestamps();
        });

        // Kategori awal
        $now = now();
        DB::table('post_categories')->insert([
            ['name' => 'Panduan Layanan', 'slug' => 'panduan-layanan', 'badge_color' => '#1e5948', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Akademik', 'slug' => 'akademik', 'badge_color' => '#c05621', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Beasiswa', 'slug' => 'beasiswa', 'badge_color' => '#2b6cb0', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kemahasiswaan', 'slug' => 'kemahasiswaan', 'badge_color' => '#6b46c1', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pengumuman', 'slug' => 'pengumuman', 'badge_color' => '#143d32', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_categories');
    }
};

