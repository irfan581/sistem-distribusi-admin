<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_izin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('keterangan', ['Sakit', 'Cuti', 'Izin', 'Lainnya'])->default('Izin');
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_izin');
    }
};
