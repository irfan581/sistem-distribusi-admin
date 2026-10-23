<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daftar_muat_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daftar_muat_id')->constrained('daftar_muats')->onDelete('cascade');
            $table->integer('urutan')->default(1);
            $table->string('lokasi_pengiriman');
            $table->string('jenis_muatan');
            $table->decimal('total_muatan_ton', 5, 2);
            $table->string('penerima')->nullable();
            $table->string('no_telepon_penerima')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        // Hapus kolom lama dari daftar_muats (dipindah ke items)
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->dropColumn(['lokasi_pengiriman', 'jenis_muatan', 'total_muatan_ton']);
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->string('lokasi_pengiriman')->after('tanggal_muatan');
            $table->string('jenis_muatan')->after('lokasi_pengiriman');
            $table->decimal('total_muatan_ton', 5, 2)->after('jenis_muatan');
        });
        Schema::dropIfExists('daftar_muat_items');
    }
};
