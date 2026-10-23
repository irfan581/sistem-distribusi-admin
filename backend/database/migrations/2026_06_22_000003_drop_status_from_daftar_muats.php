<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->dropColumn('status_keberangkatan');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->string('status_keberangkatan', 20)->default('Standby')->after('tanggal_muatan');
        });
    }
};
