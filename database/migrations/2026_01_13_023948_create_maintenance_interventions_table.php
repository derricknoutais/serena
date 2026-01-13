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
        Schema::create('maintenance_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->index();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->index();
            $table->foreignId('created_by_user_id')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('summary')->nullable();
            $table->decimal('labor_cost', 10, 2)->default(0);
            $table->decimal('parts_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->string('currency', 3)->default('XAF');
            $table->string('accounting_status', 32)->default('draft');
            $table->timestamp('submitted_to_accounting_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id']);
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            $table->foreign('technician_id', 'maintenance_interventions_technician_id_foreign')
                ->references('id')
                ->on('technicians')
                ->nullOnDelete();
            $table->foreign('created_by_user_id', 'maintenance_interventions_created_by_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_interventions');
    }
};
