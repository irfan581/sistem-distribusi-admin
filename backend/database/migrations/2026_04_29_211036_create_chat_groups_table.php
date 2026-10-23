<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_groups', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('creator_type');
            $table->unsignedBigInteger('creator_id');
            $table->boolean('is_broadcast')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_groups');
    }
};
