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
        Schema::create('chat_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('emoji', 10);          // e.g. 👍 ❤️ 😂 😮 😢 😡
            $table->string('user_type');           // App\Models\User | App\Models\Sales
            $table->unsignedBigInteger('user_id');
            $table->timestamp('reacted_at')->useCurrent();

            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            // One reaction per emoji per user per message
            $table->unique(['message_id', 'emoji', 'user_type', 'user_id']);
            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_message_reactions');
    }
};
