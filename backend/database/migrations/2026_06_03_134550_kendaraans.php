<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kendaraans', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_kendaraan');
            $table->string('nomor_polisi')->unique();
            $table->string('merk');
            $table->string('model')->nullable();
            $table->integer('tahun_pembuatan');
            $table->decimal('kapasitas_muatan_ton', 5, 2);
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->enum('status_kendaraan', ['Tersedia', 'Tidak Tersedia'])->default('Tersedia');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kendaraans');
    }
};
