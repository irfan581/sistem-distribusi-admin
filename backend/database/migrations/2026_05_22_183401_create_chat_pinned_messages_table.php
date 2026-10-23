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
        Schema::create('chat_pinned_messages', function (Blueprint $table) {
            $table->id();
            // Room identifier
            $table->string('room_type');       // 'dm' | 'group'
            $table->string('room_key');        // DM: "dm-{minId}-{maxId}-{typeA}-{typeB}" ; group: "group-{id}"
            $table->unsignedBigInteger('message_id');
            // Who pinned it
            $table->string('pinned_by_type');  // App\Models\User | App\Models\Sales
            $table->unsignedBigInteger('pinned_by_id');
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            // Only one active pin per room
            $table->unique(['room_type', 'room_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_pinned_messages');
    }
};
