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
        Schema::table('bar_orders', function (Blueprint $table) {
            $table->timestamp('stock_consumed_at')->nullable();
            $table->timestamp('stock_returned_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bar_orders', function (Blueprint $table) {
            $table->dropColumn(['stock_consumed_at', 'stock_returned_at']);
        });
    }
};
