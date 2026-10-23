<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambah status "Bermasalah" untuk tujuan pengiriman.
 *
 * Sebelumnya satu-satunya jalan keluar selain Selesai adalah "Gagal", dan itu
 * menyesatkan admin: gagal terbaca sebagai tidak ada barang yang turun sama
 * sekali, padahal kejadian di lapangan lebih sering setengah-setengah — toko
 * cuma sanggup terima sebagian, atau barang turun tapi ada selisih.
 *
 * Aplikasi driver memakai "Bermasalah" untuk semuanya; keputusan apa yang
 * dilakukan tetap lewat telepon ke kantor. "Gagal" dipertahankan supaya baris
 * lama tetap sah.
 */
return new class extends Migration
{
    private const SEBELUM = "'Menunggu','Tiba','Selesai','Gagal'";
    private const SESUDAH = "'Menunggu','Tiba','Selesai','Gagal','Bermasalah'";

    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `daftar_muat_items`
             MODIFY COLUMN `status_bongkar` ENUM(" . self::SESUDAH . ")
             NOT NULL DEFAULT 'Menunggu'"
        );
    }

    public function down(): void
    {
        // Baris yang sudah terlanjur Bermasalah dikembalikan ke Gagal, kalau
        // tidak ALTER-nya ditolak MySQL karena ada nilai di luar enum baru.
        DB::table('daftar_muat_items')
            ->where('status_bongkar', 'Bermasalah')
            ->update(['status_bongkar' => 'Gagal']);

        DB::statement(
            "ALTER TABLE `daftar_muat_items`
             MODIFY COLUMN `status_bongkar` ENUM(" . self::SEBELUM . ")
             NOT NULL DEFAULT 'Menunggu'"
        );
    }
};
