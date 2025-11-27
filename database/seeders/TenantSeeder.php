<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = [
            [
                'name' => 'Orisha Inn',
                'slug' => 'orisha-inn',
                'contact_email' => 'owner@orisha-inn.test',
                'plan' => 'premium',
                'domain' => 'orisha-inn.serena.test',
            ],
            [
                'name' => 'Demo Hotel',
                'slug' => 'demo-hotel',
                'contact_email' => 'owner@demo-hotel.test',
                'plan' => 'standard',
                'domain' => 'demo-hotel.serena.test',
            ],
        ];

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::query()->firstOrCreate(
                [
                    'slug' => $tenantData['slug'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $tenantData['name'],
                    'contact_email' => $tenantData['contact_email'],
                    'plan' => $tenantData['plan'],
                    'data' => [
                        'name' => $tenantData['name'],
                        'slug' => $tenantData['slug'],
                        'plan' => $tenantData['plan'],
                        'contact_email' => $tenantData['contact_email'],
                    ],
                ],
            );

            $tenant->domains()->updateOrCreate(
                [
                    'domain' => $tenantData['domain'],
                ],
                [
                    'tenant_id' => $tenant->getKey(),
                ],
            );
        }
    }
}
