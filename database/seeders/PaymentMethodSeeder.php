<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $tenantId = $hotel->tenant_id;

            $definitions = [
                [
                    'name' => 'Espèces',
                    'code' => 'CASH',
                    'type' => 'cash',
                    'is_default' => true,
                ],
                [
                    'name' => 'Mobile Money',
                    'code' => 'MOMO',
                    'type' => 'mobile_money',
                ],
                [
                    'name' => 'Carte bancaire',
                    'code' => 'CARD',
                    'type' => 'card',
                ],
            ];

            foreach ($definitions as $definition) {
                PaymentMethod::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'hotel_id' => $hotel->id,
                        'code' => $definition['code'],
                    ],
                    [
                        'name' => $definition['name'],
                        'type' => $definition['type'],
                        'is_active' => true,
                        'is_default' => $definition['is_default'] ?? false,
                    ],
                );
            }
        });
    }
}
