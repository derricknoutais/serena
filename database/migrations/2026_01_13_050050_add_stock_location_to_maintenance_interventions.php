<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_interventions', function (Blueprint $table): void {
            if (! Schema::hasColumn('maintenance_interventions', 'stock_location_id')) {
                $table->unsignedBigInteger('stock_location_id')->nullable()->after('currency')->index('maintenance_interventions_stock_location_id_index');
                $table->foreign('stock_location_id', 'maintenance_interventions_stock_location_fk')
                    ->references('id')
                    ->on('storage_locations')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_interventions', function (Blueprint $table): void {
            $table->dropForeign(['stock_location_id']);
            $table->dropColumn('stock_location_id');
        });
    }
};
