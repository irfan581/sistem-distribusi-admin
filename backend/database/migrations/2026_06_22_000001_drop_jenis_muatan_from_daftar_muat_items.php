<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->dropColumn('jenis_muatan');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->string('jenis_muatan')->after('lokasi_pengiriman')->default('');
        });
    }
};
