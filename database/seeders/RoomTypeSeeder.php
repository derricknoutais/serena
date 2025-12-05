<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $types = [
                [
                    'name' => 'Chambre Classique',
                    'capacity_adults' => 2,
                    'capacity_children' => 2,
                    'base_price' => 20000,
                ],
                [
                    'name' => 'Chambre VIP',
                    'capacity_adults' => 2,
                    'capacity_children' => 2,
                    'base_price' => 30000,
                ],
            ];

            foreach ($types as $roomType) {
                RoomType::query()->firstOrCreate(
                    [
                        'tenant_id' => $hotel->tenant_id,
                        'hotel_id' => $hotel->id,
                        'name' => $roomType['name'],
                    ],
                    [
                        'capacity_adults' => $roomType['capacity_adults'],
                        'capacity_children' => $roomType['capacity_children'],
                        'base_price' => $roomType['base_price'],
                        'description' => null,
                    ],
                );
            }
        });
    }
}
