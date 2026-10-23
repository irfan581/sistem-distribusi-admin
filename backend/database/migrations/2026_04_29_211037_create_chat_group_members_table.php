<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('member_type');
            $table->unsignedBigInteger('member_id');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
            $table->index(['group_id']);
            $table->index(['member_type', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_group_members');
    }
};
