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
        Schema::create('sales_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('akurasi', 8, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_locations');
    }
};
