<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $slug = 'demo';
        $baseDomain = config('tenancy.central_domains')[0] ?? 'saas-template.test';

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Demo Tenant',
                'contact_email' => 'owner@demo.test',
                'plan' => 'standard',
                'data' => [
                    'name' => 'Demo Tenant',
                    'slug' => $slug,
                ],
            ],
        );

        Domain::query()->firstOrCreate(
            ['domain' => "{$slug}.{$baseDomain}"],
            ['tenant_id' => $tenant->getKey()],
        );

        $user = User::query()->firstOrCreate(
            ['email' => 'owner@demo.test'],
            [
                'tenant_id' => $tenant->getKey(),
                'name' => 'Demo Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_superadmin' => false,
            ],
        );

        $user->assignRole('owner');
    }
}
