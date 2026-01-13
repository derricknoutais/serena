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
        Schema::create('maintenance_intervention_ticket', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->index();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('maintenance_intervention_id')
                ->index('mikt_intervention_idx');
            $table->foreignId('maintenance_ticket_id')
                ->index('mikt_ticket_idx');
            $table->text('work_done')->nullable();
            $table->decimal('labor_cost', 10, 2)->default(0);
            $table->decimal('parts_cost', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['maintenance_intervention_id', 'maintenance_ticket_id'], 'maintenance_intervention_ticket_unique');
            $table->index(['tenant_id', 'hotel_id']);
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->foreign('maintenance_intervention_id', 'maintenance_intervention_ticket_intervention_id_foreign')
                ->references('id')
                ->on('maintenance_interventions')
                ->cascadeOnDelete();
            $table->foreign('maintenance_ticket_id', 'maintenance_intervention_ticket_ticket_id_foreign')
                ->references('id')
                ->on('maintenance_tickets')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_intervention_ticket');
    }
};
