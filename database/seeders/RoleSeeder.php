<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guardName = config('auth.defaults.guard', 'web');

        $permissions = [
            'manage_hotel',
            'manage_staff',
            'manage_bookings',
            'manage_housekeeping',
            'manage_finances',
            'view_reports',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(
                [
                    'name' => $permission,
                    'guard_name' => $guardName,
                ],
            );
        }

        $rolePermissions = [
            'owner' => $permissions,
            'manager' => [
                'manage_hotel',
                'manage_staff',
                'manage_bookings',
                'manage_housekeeping',
                'view_reports',
            ],
            'receptionist' => [
                'manage_bookings',
            ],
            'housekeeping' => [
                'manage_housekeeping',
            ],
            'accountant' => [
                'manage_finances',
                'view_reports',
            ],
        ];

        foreach ($rolePermissions as $role => $permissionSet) {
            $roleModel = Role::query()->firstOrCreate(
                [
                    'name' => $role,
                    'guard_name' => $guardName,
                ],
            );

            $roleModel->syncPermissions($permissionSet);
        }
    }
}
