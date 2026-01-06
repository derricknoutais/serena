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
        Schema::create('housekeeping_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_id')->constrained('housekeeping_checklists')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['checklist_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_checklist_items');
    }
};
