<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghidupkan kembali siklus hidup pengiriman yang hilang sejak kolom
 * status_keberangkatan dihapus di 2026_06_22_000003. Kali ini statusnya benar
 * benar dipakai: driver yang menggerakkannya dari aplikasi.
 *
 * Nama kolom sengaja berbeda dari yang lama agar tidak tertukar dengan sisa
 * kode atau query lama yang mungkin masih menyebut status_keberangkatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->enum('status_perjalanan', ['Menunggu', 'Dimuat', 'Berangkat', 'Selesai', 'Batal'])
                ->default('Menunggu')
                ->after('jenis_muatan');

            $table->timestamp('dimuat_at')->nullable()->after('status_perjalanan');
            $table->timestamp('berangkat_at')->nullable()->after('dimuat_at');
            $table->timestamp('selesai_at')->nullable()->after('berangkat_at');

            // Endpoint tugas hari ini menyaring tepat pada dua kolom ini.
            $table->index(['driver_id', 'tanggal_muatan'], 'daftar_muats_driver_tanggal_idx');
            $table->index(['status_perjalanan', 'tanggal_muatan'], 'daftar_muats_status_tanggal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->dropIndex('daftar_muats_driver_tanggal_idx');
            $table->dropIndex('daftar_muats_status_tanggal_idx');
            $table->dropColumn(['status_perjalanan', 'dimuat_at', 'berangkat_at', 'selesai_at']);
        });
    }
};
