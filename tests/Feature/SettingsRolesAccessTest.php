<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('allows owner and manager to access roles settings', function (string $role) {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Test Hotel',
        'slug' => 'test-hotel',
        'plan' => 'standard',
        'contact_email' => 'contact@test-hotel.test',
        'data' => [
            'name' => 'Test Hotel',
            'slug' => 'test-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'test-hotel.serena.test']);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
    ]);

    $user->assignRole($role);

    $domain = 'test-hotel.serena.test';

    $this->actingAs($user)
        ->get("http://{$domain}/settings/roles")
        ->assertOk();
})->with([
    'owner',
    'manager',
    'superadmin',
]);

it('isolates roles settings per tenant domain', function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenantA = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Hotel Alpha',
        'slug' => 'hotel-alpha',
        'plan' => 'standard',
        'contact_email' => 'alpha@test.test',
        'data' => [
            'name' => 'Hotel Alpha',
            'slug' => 'hotel-alpha',
        ],
    ]);
    $tenantA->domains()->create(['domain' => 'alpha.serena.test']);

    $tenantB = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Hotel Beta',
        'slug' => 'hotel-beta',
        'plan' => 'standard',
        'contact_email' => 'beta@test.test',
        'data' => [
            'name' => 'Hotel Beta',
            'slug' => 'hotel-beta',
        ],
    ]);
    $tenantB->domains()->create(['domain' => 'beta.serena.test']);

    $userA = User::factory()->create([
        'tenant_id' => $tenantA->getKey(),
        'email' => 'owner@alpha.test',
    ]);
    $userA->assignRole('owner');

    User::factory()->create([
        'tenant_id' => $tenantB->getKey(),
        'email' => 'owner@beta.test',
    ]);

    $domain = 'alpha.serena.test';

    $this->actingAs($userA)
        ->get("http://{$domain}/settings/roles")
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $page->component('settings/Roles');

            $users = collect($page->toArray()['props']['users'] ?? []);

            expect($users->pluck('email'))->toContain('owner@alpha.test');
            expect($users->pluck('email'))->not->toContain('owner@beta.test');
        });
});
