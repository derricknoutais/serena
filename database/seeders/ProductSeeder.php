<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $barCategory = ProductCategory::query()->firstOrCreate(
                [
                    'tenant_id' => $hotel->tenant_id,
                    'hotel_id' => $hotel->id,
                ],
                [
                    'name' => 'Bar',
                    'description' => 'Boissons alcoolisées et softs',
                    'is_active' => true,
                ],
            );

            $restaurantCategory = ProductCategory::query()->firstOrCreate(
                [
                    'tenant_id' => $hotel->tenant_id,
                    'hotel_id' => $hotel->id,
                ],
                [
                    'name' => 'Restaurant',
                    'description' => 'Plats, snacks et room service',
                    'is_active' => true,
                ],
            );

            $barProducts = [
                ['name' => 'Bière pression', 'sku' => 'BAR-BEER-DRAFT', 'unit_price' => 2000],
                ['name' => 'Cocktail maison', 'sku' => 'BAR-COCKTAIL-HOUSE', 'unit_price' => 3500],
                ['name' => 'Soda 33cl', 'sku' => 'BAR-SODA-33', 'unit_price' => 1000],
            ];

            foreach ($barProducts as $product) {
                Product::query()->firstOrCreate(
                    [
                        'tenant_id' => $hotel->tenant_id,
                        'hotel_id' => $hotel->id,
                        'sku' => $product['sku'],
                    ],
                    [
                        'product_category_id' => $barCategory->id,
                        'name' => $product['name'],
                        'unit_price' => $product['unit_price'],
                        'tax_id' => null,
                        'account_code' => 'BAR',
                        'is_active' => true,
                    ],
                );
            }

            $restaurantProducts = [
                ['name' => 'Plat du jour', 'sku' => 'RESTO-DISH-DAY', 'unit_price' => 5000],
                ['name' => 'Sandwich club', 'sku' => 'RESTO-SANDWICH-CLUB', 'unit_price' => 3500],
                ['name' => 'Petit-déjeuner continental', 'sku' => 'RESTO-BREAKFAST-CONT', 'unit_price' => 4000],
            ];

            foreach ($restaurantProducts as $product) {
                Product::query()->firstOrCreate(
                    [
                        'tenant_id' => $hotel->tenant_id,
                        'hotel_id' => $hotel->id,
                        'sku' => $product['sku'],
                    ],
                    [
                        'product_category_id' => $restaurantCategory->id,
                        'name' => $product['name'],
                        'unit_price' => $product['unit_price'],
                        'tax_id' => null,
                        'account_code' => 'RESTO',
                        'is_active' => true,
                    ],
                );
            }
        });
    }
}
