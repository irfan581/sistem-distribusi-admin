<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_visit_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('sales')->cascadeOnDelete();
            $table->unsignedTinyInteger('target_per_day')->default(5);
            $table->string('period_type')->default('daily'); // daily | monthly | custom
            $table->string('period_label')->nullable();       // e.g. "Mei 2026"
            $table->boolean('is_active')->default(true);
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sales_id', 'is_active']);
            $table->index('effective_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_visit_targets');
    }
};
