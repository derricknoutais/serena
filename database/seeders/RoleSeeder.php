<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guardName = config('auth.defaults.guard', 'web');

        foreach ([
            'owner',
            'manager',
            'supervisor',
            'receptionist',
            'housekeeping',
            'accountant',
            'superadmin',
            'member',
        ] as $role) {
            Role::query()->firstOrCreate(
                [
                    'name' => $role,
                    'guard_name' => $guardName,
                ],
            );
        }
    }
}
