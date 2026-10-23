<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->foreignId('toko_id')
                  ->nullable()
                  ->after('sales_id')
                  ->constrained('tokos')
                  ->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->dropForeign(['toko_id']);
            $table->dropColumn('toko_id');
        });
    }
};