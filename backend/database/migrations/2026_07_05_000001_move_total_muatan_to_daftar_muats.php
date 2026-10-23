<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Total muatan sekarang dihitung sekali per rencana (seluruh truk),
        // bukan per toko lagi.
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->decimal('total_muatan_ton', 8, 3)->default(0)->after('jenis_muatan');
        });

        // Pindahkan total lama (jumlah Kg per toko) ke level rencana.
        DB::statement('
            UPDATE daftar_muats dm
            SET total_muatan_ton = (
                SELECT COALESCE(SUM(i.total_muatan_ton), 0)
                FROM daftar_muat_items i
                WHERE i.daftar_muat_id = dm.id
            )
        ');

        // Kg per toko tidak dipakai lagi — jadikan nullable (nama toko tetap ada).
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->decimal('total_muatan_ton', 8, 3)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->decimal('total_muatan_ton', 8, 3)->default(0)->change();
        });

        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->dropColumn('total_muatan_ton');
        });
    }
};
