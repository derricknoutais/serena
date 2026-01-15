<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_intervention_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('maintenance_intervention_id');
            $table->unsignedBigInteger('stock_item_id');
            $table->unsignedBigInteger('storage_location_id');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id']);
            $table->index('maintenance_intervention_id');
            $table->index('stock_item_id');
            $table->index('storage_location_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
            $table->foreign('maintenance_intervention_id', 'mii_intervention_fk')
                ->references('id')
                ->on('maintenance_interventions')
                ->cascadeOnDelete();
            $table->foreign('stock_item_id', 'mii_stockitem_fk')
                ->references('id')
                ->on('stock_items')
                ->cascadeOnDelete();
            $table->foreign('storage_location_id', 'mii_storage_fk')
                ->references('id')
                ->on('storage_locations')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id', 'mii_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_intervention_items');
    }
};
