<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->index('tanggal_muatan',       'daftar_muats_tanggal_idx');
            $table->index('status_keberangkatan', 'daftar_muats_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->dropIndex('daftar_muats_tanggal_idx');
            $table->dropIndex('daftar_muats_status_idx');
        });
    }
};
