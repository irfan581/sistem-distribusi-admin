<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * toko_id sebelumnya sengaja tanpa constraint karena hasil fuzzy-match
     * otomatis (lihat migrasi 2026_08_24_130001). Sejak toko_id sekarang
     * dipilih langsung oleh admin saat membuat daftar muat, pengisiannya
     * deterministik — FK asli aman dipasang.
     */
    public function up(): void
    {
        // Longgarkan dulu baris lama yang toko_id-nya menunjuk toko yang
        // sudah terhapus, supaya constraint di bawah tidak gagal dipasang.
        DB::statement(
            'UPDATE daftar_muat_items SET toko_id = NULL '.
            'WHERE toko_id IS NOT NULL AND toko_id NOT IN (SELECT id FROM tokos)'
        );

        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->foreign('toko_id')->references('id')->on('tokos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->dropForeign(['toko_id']);
        });
    }
};
