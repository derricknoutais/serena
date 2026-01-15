<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_on_hand', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('storage_location_id');
            $table->unsignedBigInteger('stock_item_id');
            $table->decimal('quantity_on_hand', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id']);
            $table->unique(['tenant_id', 'hotel_id', 'storage_location_id', 'stock_item_id'], 'stock_on_hand_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
            $table->foreign('storage_location_id')->references('id')->on('storage_locations')->cascadeOnDelete();
            $table->foreign('stock_item_id')->references('id')->on('stock_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_on_hand');
    }
};
