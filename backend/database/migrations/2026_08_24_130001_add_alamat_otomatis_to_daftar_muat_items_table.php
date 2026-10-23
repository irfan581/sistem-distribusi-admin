<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alamat otomatis dicocokkan dari lokasi_pengiriman (teks bebas) ke master
     * toko, lalu diambil dari kunjungan_tokos terakhir milik toko itu — bukan
     * kolom baru di tokos, karena tokos sengaja dibiarkan tanpa alamat.
     * toko_id di sini murni hasil pencocokan nama, bukan input manual, jadi
     * dibiarkan lepas dari foreign key constraint supaya toko boleh dihapus
     * tanpa menyeret baris pengiriman lama.
     */
    public function up(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->unsignedBigInteger('toko_id')->nullable()->after('lokasi_pengiriman');
            $table->string('alamat_otomatis')->nullable()->after('toko_id');
            $table->decimal('alamat_lat', 10, 7)->nullable()->after('alamat_otomatis');
            $table->decimal('alamat_lng', 10, 7)->nullable()->after('alamat_lat');

            // Null berarti belum pernah dicoba dicocokkan. Sudah diisi (meski
            // hasilnya tetap null) berarti sudah dicoba dan tidak usah diulang
            // tiap request — hemat query similar_text yang jalan di PHP.
            $table->timestamp('alamat_resolved_at')->nullable()->after('alamat_lng');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->dropColumn(['toko_id', 'alamat_otomatis', 'alamat_lat', 'alamat_lng', 'alamat_resolved_at']);
        });
    }
};
