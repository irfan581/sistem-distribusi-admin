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
        Schema::create('chat_message_stars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('user_type');          // App\Models\User atau App\Models\Sales
            $table->unsignedBigInteger('user_id');
            $table->timestamp('starred_at')->useCurrent();

            $table->unique(['message_id', 'user_type', 'user_id']);
            $table->index(['user_type', 'user_id']);
            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_message_stars');
    }
};
