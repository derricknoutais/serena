<?php

namespace Database\Seeders;

use App\Support\PermissionsCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $permissions = PermissionsCatalog::all();

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => $guard],
            );
        }

        $roleMap = PermissionsCatalog::roleMap();

        foreach ($roleMap as $roleName => $allowed) {
            /** @var Role $role */
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();
            if (! $role) {
                continue;
            }

            foreach ($allowed as $permissionName) {
                $permission = Permission::query()->firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => $guard,
                ]);
                $role->givePermissionTo($permission);
            }
        }
    }
}
