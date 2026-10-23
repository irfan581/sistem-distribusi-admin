<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wilayah pengiriman sebagai data, bukan lagi larik yang ditulis mati.
     *
     * Daftarnya sudah dipakai sejak lama — tertanam di DaftarMuatController
     * untuk menerjemahkan kode area jadi nama wilayah di halaman statistik.
     * Karena berbentuk kode, tidak ada yang bisa menandai wilayah mana yang
     * jauh dan mana yang dekat tanpa mengubah program, padahal penandaan itu
     * yang menentukan ongkos sopir dan kategori di riwayat kiriman.
     *
     * kategori_jarak sengaja nullable: wilayah baru muncul tanpa penanda, dan
     * riwayat menampilkannya apa adanya sebagai "belum ditandai" — lebih jujur
     * daripada memberi nilai bawaan yang mungkin salah lalu terlanjur dipakai
     * menghitung ongkos.
     */
    public function up(): void
    {
        Schema::create('wilayahs', function (Blueprint $table) {
            $table->id();

            $table->string('nama', 100)->unique();

            // Kode 3-4 huruf yang dipakai admin di daftar muat (NGW, TTSJ).
            // Nullable karena ada wilayah di master toko yang belum pernah
            // muncul sebagai kode area, mis. Blora.
            $table->string('kode', 8)->nullable()->unique();

            $table->enum('kategori_jarak', ['dekat', 'jauh'])->nullable();

            // Disediakan tapi tidak ditampilkan di layar mana pun: urusan
            // ongkos tetap dibicarakan langsung antara sopir dan admin
            // keuangan. Ada di sini supaya kalau suatu saat dibutuhkan,
            // tabelnya tidak perlu dibongkar saat sudah berisi data.
            $table->decimal('ongkos', 12, 2)->nullable();

            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayahs');
    }
};
