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
        Schema::create('drivers', function (Blueprint $table){
            $table->id();
            $table->string('nama_driver');
            $table->string('no_telepon');
            $table->string('alamat');
            $table->enum('status_kerja', ['Aktif', 'Tidak Aktif'])->default('Aktif');
            $table->enum('jenis_sim', ['A', 'B1', 'B1 Umum', 'B2', 'B2 Umum'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
