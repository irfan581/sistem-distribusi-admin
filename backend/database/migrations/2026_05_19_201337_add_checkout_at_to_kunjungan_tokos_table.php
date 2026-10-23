<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->timestamp('checkout_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            $table->dropColumn('checkout_at');
        });
    }
};
