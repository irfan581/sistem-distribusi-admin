<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alamat yang diketik admin lebih rinci dari alamat hasil GPS kunjungan,
     * jadi ditandai supaya tidak ditimpa kunjungan berikutnya. Titik GPS-nya
     * tetap boleh terisi dari kunjungan.
     */
    public function up(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->boolean('alamat_manual')->default(false)->after('alamat');
        });
    }

    public function down(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->dropColumn('alamat_manual');
        });
    }
};
