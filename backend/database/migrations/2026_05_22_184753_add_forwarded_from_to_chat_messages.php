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
            // nullable FK to original message that was forwarded
            $table->unsignedBigInteger('forwarded_from_id')->nullable()->after('reply_to_id');
            $table->foreign('forwarded_from_id')->references('id')->on('chat_messages')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['forwarded_from_id']);
            $table->dropColumn('forwarded_from_id');
        });
    }
};
