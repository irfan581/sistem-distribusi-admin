<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->string('voter_type');
            $table->unsignedBigInteger('voter_id');
            $table->unsignedInteger('pilihan_index');
            $table->timestamps();
            $table->index('poll_id');
            $table->unique(['poll_id', 'voter_type', 'voter_id', 'pilihan_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_poll_votes');
    }
};
