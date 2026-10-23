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
        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            // Allow null — foto bisa kosong jika upload gagal di tengah jalan
            $table->string('foto_kunjungan')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->string('foto_kunjungan')->nullable(false)->change();
        });
    }
};
