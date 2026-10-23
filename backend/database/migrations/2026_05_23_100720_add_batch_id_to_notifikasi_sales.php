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
        Schema::table('notifikasi_sales', function (Blueprint $table) {
            // UUID untuk mengelompokkan notif yang dikirim 1 batch broadcast
            $table->uuid('batch_id')->nullable()->after('is_read')->index();
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi_sales', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
    }
};
