<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->enum('jenis_muatan', ['subsidi', 'non_subsidi'])
                  ->default('non_subsidi')
                  ->after('lokasi_pengiriman');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->dropColumn('jenis_muatan');
        });
    }
};
