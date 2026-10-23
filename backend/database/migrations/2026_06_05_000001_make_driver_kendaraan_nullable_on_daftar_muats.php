<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->foreignId('kendaraan_id')->nullable()->change();
            $table->foreignId('driver_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->foreignId('kendaraan_id')->nullable(false)->change();
            $table->foreignId('driver_id')->nullable(false)->change();
        });
    }
};
