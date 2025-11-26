<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $owner = Role::findOrCreate('owner', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $member = Role::findOrCreate('member', 'web');

        $permissions = Permission::all()->keyBy('name');

        $owner->syncPermissions($permissions->keys()->all());

        $admin->syncPermissions([
            'dashboard.view',
            'users.view',
            'users.manage',
            'invitations.view',
            'invitations.manage',
            'profile.update',
            'activity.view',
        ]);

        $member->syncPermissions([
            'dashboard.view',
            'users.view',
            'invitations.view',
            'profile.update',
        ]);
    }
}
