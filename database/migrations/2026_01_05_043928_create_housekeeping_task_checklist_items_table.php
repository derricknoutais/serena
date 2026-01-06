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
        Schema::create('housekeeping_task_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('housekeeping_tasks')->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained('housekeeping_checklist_items')->cascadeOnDelete();
            $table->boolean('is_ok')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'checklist_item_id'], 'hk_task_checklist_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_task_checklist_items');
    }
};
