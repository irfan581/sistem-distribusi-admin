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
        Schema::create('daftar_muats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kendaraan_id')->constrained('kendaraans')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->date('tanggal_muatan');
            $table->string('lokasi_pengiriman');
            $table->string('jenis_muatan');
            $table->decimal('total_muatan_ton', 5, 2);
            $table->string('status_keberangkatan')->default('Standby');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daftar_muats');
    }
};
