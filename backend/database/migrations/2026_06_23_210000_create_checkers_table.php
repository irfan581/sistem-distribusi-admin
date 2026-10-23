<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkers', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->enum('status', ['Aktif', 'Tidak Aktif'])->default('Aktif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkers');
    }
};
