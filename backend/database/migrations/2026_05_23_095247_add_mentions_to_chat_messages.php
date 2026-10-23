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
            // JSON array of mentioned users — [{"type":"admin","id":1,"nama":"Budi"}, ...]
            $table->json('mentions')->nullable()->after('reply_to_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('mentions');
        });
    }
};
