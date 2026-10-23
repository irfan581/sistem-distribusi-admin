<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('presensis', function (Blueprint $table) {
            $table->string('foto_masuk')->nullable()->default(null)->change();
            $table->string('foto_pulang')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('presensis', function (Blueprint $table) {
            $table->string('foto_masuk')->nullable(false)->change();
            $table->string('foto_pulang')->nullable(false)->change();
        });
    }
};
