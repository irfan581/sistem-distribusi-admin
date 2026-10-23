<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daftar_muat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daftar_muat_id')->constrained('daftar_muats')->onDelete('cascade');
            $table->string('status');
            $table->string('keterangan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daftar_muat_logs');
    }
};
