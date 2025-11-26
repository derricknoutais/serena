<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            [
                'email' => 'superadmin@saas-template.test',
            ],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'email_verified_at' => now(),
                'tenant_id' => User::factory()->create()->tenant_id,
            ],
        );

        $user->assignRole('superadmin');
    }
}
