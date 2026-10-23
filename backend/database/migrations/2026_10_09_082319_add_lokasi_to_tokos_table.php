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
        Schema::table('tokos', function (Blueprint $table) {
            $table->string ('alamat')->nullable()->after('wilayah');
            $table->double ('lat')->nullable()->after('alamat');
            $table->double ('lng')->nullable()->after('lat');

            // 'kunjungan' = otomatis dari kunjungan sales, 'manual' = dikoreksi orang
            $table->string ('lokasi_sumber')->nullable()->after('lng');
            $table->timestamp ('lokasi_diperbarui_at')->nullable()->after('lokasi_sumber');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->dropColumn(['alamat', 'lat','lng', 'lokasi_sumber', 'lokasi_diperbarui_at']);
        });
    }
};
