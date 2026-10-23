<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensis', function (Blueprint $table) {
            $table->string('alamat_masuk')->nullable()->after('location_masuk');
            $table->string('alamat_pulang')->nullable()->after('location_pulang');
        });

        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->string('alamat')->nullable()->after('lng');
        });
    }

    public function down(): void
    {
        Schema::table('presensis', function (Blueprint $table) {
            $table->dropColumn(['alamat_masuk', 'alamat_pulang']);
        });

        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->dropColumn('alamat');
        });
    }
};
