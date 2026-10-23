<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->tinyInteger('nomor_rit')->default(1)->after('tanggal_muatan');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->dropColumn('nomor_rit');
        });
    }
};
