<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_item_components', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('kit_stock_item_id');
            $table->unsignedBigInteger('component_stock_item_id');
            $table->decimal('quantity', 10, 2);
            $table->timestamps();

            $table->foreign('kit_stock_item_id')
                ->references('id')
                ->on('stock_items')
                ->cascadeOnDelete();
            $table->foreign('component_stock_item_id')
                ->references('id')
                ->on('stock_items')
                ->restrictOnDelete();

            $table->unique(['kit_stock_item_id', 'component_stock_item_id'], 'stock_item_component_unique');
            $table->index(['tenant_id', 'hotel_id', 'kit_stock_item_id'], 'stock_item_components_tenant_hotel_kit_idx');
            $table->index(['tenant_id', 'hotel_id', 'component_stock_item_id'], 'stock_item_components_tenant_hotel_component_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_item_components');
    }
};
