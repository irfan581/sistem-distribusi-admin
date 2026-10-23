<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('izins', function (Blueprint $table) {
            $table->index('tanggal',        'izins_tanggal_idx');
            $table->index('sampai_tanggal', 'izins_sampai_tanggal_idx');
            $table->index('status',         'izins_status_idx');
            $table->index('sales_id',       'izins_sales_id_idx');
        });

        Schema::table('presensis', function (Blueprint $table) {
            $table->index('tanggal',  'presensis_tanggal_idx');
            $table->index('sales_id', 'presensis_sales_id_idx');
        });

        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->index('sales_id',  'kunjungan_tokos_sales_id_idx');
            $table->index('created_at','kunjungan_tokos_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('izins', function (Blueprint $table) {
            $table->dropIndex('izins_tanggal_idx');
            $table->dropIndex('izins_sampai_tanggal_idx');
            $table->dropIndex('izins_status_idx');
            $table->dropIndex('izins_sales_id_idx');
        });

        Schema::table('presensis', function (Blueprint $table) {
            $table->dropIndex('presensis_tanggal_idx');
            $table->dropIndex('presensis_sales_id_idx');
        });

        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->dropIndex('kunjungan_tokos_sales_id_idx');
            $table->dropIndex('kunjungan_tokos_created_at_idx');
        });
    }
};
