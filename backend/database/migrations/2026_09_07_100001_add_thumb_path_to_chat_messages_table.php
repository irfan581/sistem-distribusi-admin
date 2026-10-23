<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versi kecil dari gambar sebuah pesan chat.
     *
     * Sampai sekarang gelembung chat memuat berkas ukuran penuh, padahal
     * lebarnya cuma 300px — dua puluh foto sekali gulir berarti 6,5 MB. Foto
     * bongkar sudah punya thumbnail 320px sejak `bongkar:thumbnail`; kolom ini
     * yang membawanya ikut ke chat.
     *
     * Nullable dengan sengaja: pesan gambar biasa (bukan laporan bongkar)
     * tidak punya thumbnail, dan pesan lama juga tidak. Yang null jatuh
     * kembali ke berkas penuh — lebih berat, tapi tetap tampil.
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('thumb_path', 255)->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('thumb_path');
        });
    }
};
