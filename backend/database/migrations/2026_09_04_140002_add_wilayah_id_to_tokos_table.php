<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyambungkan toko ke master wilayah.
     *
     * Kolom teks `wilayah` sengaja DIPERTAHANKAN, tidak dihapus: dipakai
     * pencarian, pengurutan, unggah massal, dan aplikasi sales. Menghapusnya
     * sekarang berarti membongkar semuanya sekaligus. Yang baru menjadi sumber
     * kebenaran, yang lama tetap terisi agar tidak ada yang patah — sampai
     * seluruh pemakainya dipindahkan.
     */
    public function up(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->foreignId('wilayah_id')
                ->nullable()
                ->after('wilayah')
                ->constrained('wilayahs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wilayah_id');
        });
    }
};
