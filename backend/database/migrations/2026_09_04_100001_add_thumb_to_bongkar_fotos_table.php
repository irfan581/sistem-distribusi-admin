<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto bukti disimpan 1280px kualitas 80 — sekitar 200-400 KB per lembar.
     * Aplikasi sales menampilkannya berderet dalam satu aliran gaya grup
     * WhatsApp, jadi satu layar bisa menyeret belasan foto sekaligus; tanpa
     * versi kecil, sekali gulir menghabiskan megabyte-an kuota di lapangan.
     *
     * Kolomnya nullable dan bukan hasil tebakan dari nama berkas: foto lama
     * belum punya thumbnail sampai `bongkar:thumbnail` dijalankan, dan yang
     * masih null jatuh kembali ke foto ukuran penuh alih-alih gambar rusak.
     */
    public function up(): void
    {
        Schema::table('bongkar_fotos', function (Blueprint $table) {
            $table->string('thumb', 255)->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('bongkar_fotos', function (Blueprint $table) {
            $table->dropColumn('thumb');
        });
    }
};
