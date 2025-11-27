<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::query()->firstOrCreate(
            [
                'name' => 'superadmin',
                'guard_name' => config('auth.defaults.guard', 'web'),
            ],
        );

        $user = User::query()->firstOrCreate(
            [
                'email' => 'superadmin@saas-template.test',
            ],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'email_verified_at' => now(),
                'tenant_id' => User::factory()->create()->tenant_id,
                'is_superadmin' => true,
            ],
        );

        $user->assignRole($superAdminRole);
        $user->forceFill(['is_superadmin' => true])->save();
    }
}
