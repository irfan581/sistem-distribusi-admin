<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $nomor = 0;

        DB::table('drivers')->orderBy('id')->select('id', 'kode_driver')->each(
            function ($driver) use (&$nomor) {
                $nomor++;

                if (! empty($driver->kode_driver)) {
                    return;
                }

                DB::table('drivers')
                    ->where('id', $driver->id)
                    ->update(['kode_driver' => 'DRV-' . str_pad((string) $nomor, 3, '0', STR_PAD_LEFT)]);
            }
        );

        // Setelah semua terisi, kode wajib ada — ini identitas login driver.
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('kode_driver', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('kode_driver', 20)->nullable()->change();
        });

        DB::table('drivers')->update(['kode_driver' => null]);
    }
};
