<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_id');
            $table->string('receiver_type')->nullable();
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->enum('type', ['dm', 'group'])->default('dm');
            $table->enum('message_type', ['text', 'image', 'file', 'poll'])->default('text');
            $table->text('message')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('poll_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
