<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('visit_target_id')->nullable()->constrained('sales_visit_targets')->nullOnDelete();
            $table->unsignedTinyInteger('old_target')->nullable();
            $table->unsignedTinyInteger('new_target');
            $table->string('changed_by_type')->default('user'); // user | system
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->default('update'); // create | update | reset | bulk_update
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable(); // extra context (bulk op id, source, etc.)
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['sales_id', 'changed_at']);
            $table->index('changed_by_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_change_logs');
    }
};
