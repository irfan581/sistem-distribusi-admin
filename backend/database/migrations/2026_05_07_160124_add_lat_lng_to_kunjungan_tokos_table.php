<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->double('lat', 10, 7)->nullable()->after('location');
            $table->double('lng', 10, 7)->nullable()->after('lat');
            $table->index(['lat', 'lng']);
        });

        // Isi lat/lng dari kolom location yang ada (format "lat,lng").
        // SUBSTRING_INDEX khusus MySQL; pada SQLite (test suite) tabelnya selalu
        // kosong di titik ini sehingga backfill memang tidak ada gunanya.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            UPDATE kunjungan_tokos
            SET
                lat = CAST(SUBSTRING_INDEX(location, ',', 1) AS DECIMAL(10,7)),
                lng = CAST(SUBSTRING_INDEX(location, ',', -1) AS DECIMAL(10,7))
            WHERE location IS NOT NULL
              AND location != ''
              AND location LIKE '%,%'
        ");
    }

    public function down(): void
    {
        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->dropIndex(['lat', 'lng']);
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
