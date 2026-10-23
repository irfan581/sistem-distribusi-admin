<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->decimal('total_muatan_ton', 8, 3)->change();
        });

        Schema::table('kendaraans', function (Blueprint $table) {
            $table->decimal('kapasitas_muatan_ton', 8, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->decimal('total_muatan_ton', 5, 2)->change();
        });

        Schema::table('kendaraans', function (Blueprint $table) {
            $table->decimal('kapasitas_muatan_ton', 5, 2)->change();
        });
    }
};
