<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggapan admin (diterima/ditolak + catatan) atas foto kunjungan sales dan
 * laporan driver per tujuan.
 *
 * perlu_tanggapan: data yang sudah ada saat fitur ini dipasang ditandai false
 * supaya tidak menumpuk sebagai "belum ditanggapi"; data baru otomatis true.
 */
return new class extends Migration
{
    private const TABEL = ['kunjungan_tokos', 'daftar_muat_items'];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->boolean('perlu_tanggapan')->default(true);
                $table->string('tanggapan_status', 10)->nullable();
                $table->text('tanggapan_catatan')->nullable();
                $table->foreignId('ditanggapi_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('ditanggapi_pada')->nullable();
                $table->index(['perlu_tanggapan', 'tanggapan_status']);
            });

            DB::table($tabel)->update(['perlu_tanggapan' => false]);
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropIndex(['perlu_tanggapan', 'tanggapan_status']);
                $table->dropConstrainedForeignId('ditanggapi_oleh');
                $table->dropColumn(['perlu_tanggapan', 'tanggapan_status', 'tanggapan_catatan', 'ditanggapi_pada']);
            });
        }
    }
};
