<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom accuracy ke tabel presensis
        Schema::table('presensis', function (Blueprint $table) {
            $table->float('accuracy_masuk')->nullable()->after('location_masuk')
                  ->comment('Akurasi GPS saat absen masuk (meter)');
            $table->boolean('is_suspicious_masuk')->default(false)->after('accuracy_masuk')
                  ->comment('Koordinat masuk terdeteksi di luar Indonesia');
            $table->float('accuracy_pulang')->nullable()->after('location_pulang')
                  ->comment('Akurasi GPS saat absen pulang (meter)');
            $table->boolean('is_suspicious_pulang')->default(false)->after('accuracy_pulang')
                  ->comment('Koordinat pulang terdeteksi di luar Indonesia');
        });

        // Tambah kolom accuracy ke tabel kunjungan_tokos
        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->float('accuracy')->nullable()->after('lng')
                  ->comment('Akurasi GPS saat kunjungan (meter)');
        });
    }

    public function down(): void
    {
        Schema::table('presensis', function (Blueprint $table) {
            $table->dropColumn([
                'accuracy_masuk', 'is_suspicious_masuk',
                'accuracy_pulang', 'is_suspicious_pulang',
            ]);
        });

        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->dropColumn('accuracy');
        });
    }
};
