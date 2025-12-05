<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $categories = [
                [
                    'name' => 'Bar',
                    'description' => 'Boissons alcoolisées et softs',
                ],
                [
                    'name' => 'Restaurant',
                    'description' => 'Plats, snacks et room service',
                ],
            ];

            foreach ($categories as $category) {
                ProductCategory::query()->firstOrCreate(
                    [
                        'tenant_id' => $hotel->tenant_id,
                        'hotel_id' => $hotel->id,
                    ],
                    [
                        'name' => $category['name'],
                        'description' => $category['description'],
                        'is_active' => true,
                    ],
                );
            }
        });
    }
}
