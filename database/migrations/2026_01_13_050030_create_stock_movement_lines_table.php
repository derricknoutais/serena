<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movement_lines', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('stock_movement_id');
            $table->unsignedBigInteger('stock_item_id');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->string('currency', 3)->default('XAF');
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id']);
            $table->index('stock_movement_id');
            $table->index('stock_item_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements')->cascadeOnDelete();
            $table->foreign('stock_item_id')->references('id')->on('stock_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_lines');
    }
};
