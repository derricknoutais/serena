<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TenantSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;

it('seeds tenants, roles, and users for the HMS', function () {
    $this->seed([
        RoleSeeder::class,
        TenantSeeder::class,
        UserSeeder::class,
    ]);

    $guardName = config('auth.defaults.guard', 'web');

    $expectedRoles = ['owner', 'manager', 'supervisor', 'receptionist', 'housekeeping', 'accountant'];

    $roleNames = Role::query()
        ->whereIn('name', $expectedRoles)
        ->where('guard_name', $guardName)
        ->pluck('name')
        ->all();

    expect($roleNames)->toEqualCanonicalizing($expectedRoles);

    $tenants = Tenant::query()->with('domains')->get();
    expect($tenants)->toHaveCount(2);

    $tenants->each(function (Tenant $tenant) use ($expectedRoles): void {
        expect($tenant->domains)->not->toBeEmpty();

        foreach ($expectedRoles as $role) {
            $user = User::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('email', "{$role}+{$tenant->slug}@example.com")
                ->first();

            expect($user)->not->toBeNull();
            expect($user->hasRole($role))->toBeTrue();
        }
    });
});
