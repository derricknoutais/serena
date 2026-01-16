<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->boolean('is_kit')->default(false)->after('item_category');
            $table->index(['tenant_id', 'hotel_id', 'is_kit']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->dropIndex(['stock_items_tenant_id_hotel_id_is_kit_index']);
            $table->dropColumn('is_kit');
        });
    }
};
