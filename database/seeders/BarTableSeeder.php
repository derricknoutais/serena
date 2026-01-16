<?php

namespace Database\Seeders;

use App\Models\BarTable;
use App\Models\Hotel;
use Illuminate\Database\Seeder;

class BarTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hotels = Hotel::query()->get(['id', 'tenant_id']);

        foreach ($hotels as $hotel) {
            $hasTables = BarTable::query()
                ->where('tenant_id', $hotel->tenant_id)
                ->where('hotel_id', $hotel->id)
                ->exists();

            if ($hasTables) {
                continue;
            }

            $sort = 0;

            for ($i = 1; $i <= 10; $i++) {
                BarTable::query()->create([
                    'tenant_id' => $hotel->tenant_id,
                    'hotel_id' => $hotel->id,
                    'name' => "Table {$i}",
                    'area' => 'Salle',
                    'capacity' => null,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]);
            }

            for ($i = 1; $i <= 5; $i++) {
                BarTable::query()->create([
                    'tenant_id' => $hotel->tenant_id,
                    'hotel_id' => $hotel->id,
                    'name' => "Terrasse {$i}",
                    'area' => 'Terrasse',
                    'capacity' => null,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]);
            }
        }
    }
}
