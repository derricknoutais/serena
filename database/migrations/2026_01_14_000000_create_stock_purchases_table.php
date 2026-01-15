<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_purchases', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('storage_location_id');
            $table->string('reference_no', 64)->nullable();
            $table->string('supplier_name', 160)->nullable();
            $table->date('purchased_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('XAF');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('received_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'hotel_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
            $table->foreign('storage_location_id')->references('id')->on('storage_locations')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('received_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_purchases');
    }
};
