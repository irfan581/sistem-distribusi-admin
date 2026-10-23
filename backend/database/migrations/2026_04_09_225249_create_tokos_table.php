<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tokos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->nullable()->constrained('sales')->onDelete('set null');
            $table->string('nama_kios');
            $table->string('wilayah');
            $table->boolean('is_new')->default(false); // Untuk menandai hasil import terbaru
            $table->timestamps();

            // Indexing untuk performa pencarian
            $table->index('sales_id');
            $table->index('wilayah');

            // PENTING: Unique constraint agar fitur WithUpserts di Excel Import berjalan
            // Ini mencegah data dobel jika nama toko & wilayahnya sama persis
            $table->unique(['nama_kios', 'wilayah'], 'toko_unique_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokos');
    }
};
