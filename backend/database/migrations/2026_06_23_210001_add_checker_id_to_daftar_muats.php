<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->foreignId('checker_id')->nullable()->after('nomor_rit')
                  ->constrained('checkers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muats', function (Blueprint $table) {
            $table->dropForeign(['checker_id']);
            $table->dropColumn('checker_id');
        });
    }
};
