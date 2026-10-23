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
        Schema::table('chat_messages', function (Blueprint $table) {
            // DM inbox: cari pesan belum dibaca untuk receiver
            $table->index(['type', 'receiver_type', 'receiver_id', 'is_read', 'is_deleted'], 'cm_dm_receiver_unread');
            // DM conversation: antara dua pihak
            $table->index(['type', 'sender_type', 'sender_id', 'receiver_type', 'receiver_id'], 'cm_dm_conversation');
            // Group messages
            $table->index(['type', 'group_id', 'is_deleted'], 'cm_group_messages');
            // Group unread per sender
            $table->index(['type', 'group_id', 'sender_type', 'sender_id', 'is_read'], 'cm_group_unread');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex('cm_dm_receiver_unread');
            $table->dropIndex('cm_dm_conversation');
            $table->dropIndex('cm_group_messages');
            $table->dropIndex('cm_group_unread');
        });
    }
};
