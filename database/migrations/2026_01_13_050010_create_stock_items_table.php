<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('hotel_id');
            $table->string('name', 160);
            $table->string('sku', 80)->nullable();
            $table->string('unit', 24)->default('unit');
            $table->string('item_category', 32)->default('maintenance');
            $table->boolean('is_active')->default(true);
            $table->decimal('default_purchase_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('XAF');
            $table->decimal('reorder_point', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id']);
            $table->index(['tenant_id', 'hotel_id', 'sku']);
            $table->unique(['tenant_id', 'hotel_id', 'name']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
