<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Offer;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $offers = [
                [
                    'name' => 'Nuitée',
                    'kind' => 'night',
                ],
                [
                    'name' => '3 Heures / Détente',
                    'kind' => 'short_stay',
                ],
                [
                    'name' => '24 Heures',
                    'kind' => 'full_day',
                ],
                [
                    'name' => 'Week-end',
                    'kind' => 'weekend',
                ],
            ];

            foreach ($offers as $offer) {
                Offer::query()->firstOrCreate(
                    [
                        'tenant_id' => $hotel->tenant_id,
                        'hotel_id' => $hotel->id,
                        'name' => $offer['name'],
                    ],
                    [
                        'kind' => $offer['kind'],
                        'is_active' => true,
                    ],
                );
            }
        });
    }
}
