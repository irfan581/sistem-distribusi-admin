<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Membedakan toko pelanggan dari entri "KANTOR" milik tiap sales. */
    public function up(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            // 'toko' = pelanggan, 'kantor' = kantor Tani Makmur sendiri
            $table->string('jenis', 20)->default('toko')->after('nama_kios')->index();
        });

        DB::table('tokos')
            ->whereRaw('UPPER(TRIM(nama_kios)) = ?', ['KANTOR'])
            ->update(['jenis' => 'kantor']);
    }

    public function down(): void
    {
        Schema::table('tokos', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
};
