<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pesan admin untuk sopir per tujuan; beda dari catatan_driver yang ditulis sopir saat bongkar. */
    public function up(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->string('catatan_admin')->nullable()->after('kode_area');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->dropColumn('catatan_admin');
        });
    }
};
