<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * @var array<string>
     */
    private array $permissions = [
        'users.view',
        'users.manage',
        'invitations.view',
        'invitations.manage',
        'profile.update',
        'dashboard.view',
        'activity.view',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
