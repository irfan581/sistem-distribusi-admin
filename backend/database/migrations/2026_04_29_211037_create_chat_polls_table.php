<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_polls', function (Blueprint $table) {
            $table->id();
            $table->string('creator_type');
            $table->unsignedBigInteger('creator_id');
            $table->text('pertanyaan');
            $table->json('pilihan');
            $table->boolean('multiple_choice')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_polls');
    }
};
