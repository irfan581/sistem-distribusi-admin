<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 50)->unique();         // hrd, spv, keuangan, dll
            $table->string('label', 100);                  // Label tampilan: "HRD", "Supervisor", dll
            $table->foreignId('parent_id')                 // null = role utama, isi = sub-role
                  ->nullable()
                  ->constrained('roles')
                  ->nullOnDelete();
            $table->unsignedTinyInteger('level')->default(0); // 0=owner, 1=boss, 2=admin, 3=sub-admin
            $table->json('permissions')->nullable();        // permission default untuk role ini
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};