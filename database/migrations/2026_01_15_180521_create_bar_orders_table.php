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
        Schema::create('bar_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('hotel_id');
            $table->unsignedBigInteger('bar_table_id')->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('cashier_user_id')->nullable();
            $table->timestamps();

            $table->foreign('bar_table_id')->references('id')->on('bar_tables')->nullOnDelete();
            $table->foreign('cashier_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'hotel_id'], 'bar_orders_tenant_hotel_index');
            $table->index(['tenant_id', 'hotel_id', 'bar_table_id', 'status'], 'bar_orders_table_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_orders');
    }
};
