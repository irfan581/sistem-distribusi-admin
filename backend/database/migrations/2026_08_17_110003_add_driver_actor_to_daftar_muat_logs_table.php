<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat rit sampai sekarang hanya bisa mencatat admin, karena user_id
 * menunjuk tabel users. Begitu driver ikut menggerakkan status, pelakunya harus
 * bisa dicatat juga — dan tidak boleh dipaksakan ke kolom user_id, karena
 * drivers adalah tabel yang sama sekali berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_muat_logs', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('user_id')
                ->constrained('drivers')->nullOnDelete();

            $table->enum('aktor', ['admin', 'driver', 'sistem'])->default('admin')->after('driver_id');

            // Titik saat driver menekan tombol, berguna saat ada sengketa
            // "katanya sudah sampai".
            $table->decimal('latitude', 10, 7)->nullable()->after('aktor');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            $table->index(['daftar_muat_id', 'id'], 'daftar_muat_logs_muat_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muat_logs', function (Blueprint $table) {
            $table->dropIndex('daftar_muat_logs_muat_id_idx');
            $table->dropForeign(['driver_id']);
            $table->dropColumn(['driver_id', 'aktor', 'latitude', 'longitude']);
        });
    }
};
