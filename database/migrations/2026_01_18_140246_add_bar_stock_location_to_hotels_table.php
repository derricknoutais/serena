<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->foreignId('default_bar_stock_location_id')
                ->nullable()
                ->constrained('storage_locations')
                ->nullOnDelete();
        });

        $hotels = DB::table('hotels')->select(['id', 'tenant_id', 'default_bar_stock_location_id'])->get();

        foreach ($hotels as $hotel) {
            $existingLocation = DB::table('storage_locations')
                ->where('tenant_id', $hotel->tenant_id)
                ->where('hotel_id', $hotel->id)
                ->where('category', 'bar')
                ->orderBy('id')
                ->first();

            $locationId = $existingLocation?->id;

            if (! $locationId) {
                $locationId = DB::table('storage_locations')->insertGetId([
                    'tenant_id' => $hotel->tenant_id,
                    'hotel_id' => $hotel->id,
                    'name' => 'Bar',
                    'code' => null,
                    'category' => 'bar',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($hotel->default_bar_stock_location_id === null) {
                DB::table('hotels')
                    ->where('id', $hotel->id)
                    ->update(['default_bar_stock_location_id' => $locationId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_bar_stock_location_id');
        });
    }
};
