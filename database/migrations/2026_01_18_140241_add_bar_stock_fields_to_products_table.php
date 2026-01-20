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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('stock_item_id')->nullable()->constrained('stock_items')->nullOnDelete();
            $table->boolean('manage_stock')->default(false);
            $table->decimal('stock_quantity_per_unit', 10, 2)->default(1);
            $table->foreignId('stock_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_location_id');
            $table->dropColumn('stock_quantity_per_unit');
            $table->dropColumn('manage_stock');
            $table->dropConstrainedForeignId('stock_item_id');
        });
    }
};
