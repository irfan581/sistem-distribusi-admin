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
            $table->string('file_url')->nullable()->after('message');
            $table->string('file_name')->nullable()->after('file_url');
            $table->foreignId('reply_to_id')->nullable()->after('file_name')
                  ->constrained('chat_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_id']);
            $table->dropColumn(['file_url', 'file_name', 'reply_to_id']);
        });
    }
};
