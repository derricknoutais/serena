<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('renders resources settings for users with permission', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Resources Hotel',
        'slug' => 'resources-hotel',
        'plan' => 'standard',
        'contact_email' => 'contact@resources-hotel.test',
        'data' => [
            'name' => 'Resources Hotel',
            'slug' => 'resources-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'resources-hotel.serena.test']);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
    ]);
    $user->assignRole('owner');

    $domain = 'resources-hotel.serena.test';

    $this->actingAs($user)
        ->get("http://{$domain}/settings/resources/hotel")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Config/Hotel/HotelIndex'));
});

it('blocks resources settings without permission', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Resources Hotel',
        'slug' => 'resources-hotel',
        'plan' => 'standard',
        'contact_email' => 'contact@resources-hotel.test',
        'data' => [
            'name' => 'Resources Hotel',
            'slug' => 'resources-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'resources-hotel.serena.test']);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
    ]);
    $user->assignRole('receptionist');

    $domain = 'resources-hotel.serena.test';

    $this->actingAs($user)
        ->get("http://{$domain}/settings/resources/hotel")
        ->assertForbidden();
});

it('allows guest resources when permission is granted', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Guest Resources Hotel',
        'slug' => 'guest-resources',
        'plan' => 'standard',
        'contact_email' => 'contact@guest-resources.test',
        'data' => [
            'name' => 'Guest Resources Hotel',
            'slug' => 'guest-resources',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'guest-resources.serena.test']);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
    ]);
    $user->assignRole('owner');

    $domain = 'guest-resources.serena.test';

    $this->actingAs($user)
        ->get("http://{$domain}/settings/resources/guests")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Frontdesk/Guests/GuestsIndex'));
});

it('blocks guest resources without permission', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Guest Resources Hotel',
        'slug' => 'guest-resources',
        'plan' => 'standard',
        'contact_email' => 'contact@guest-resources.test',
        'data' => [
            'name' => 'Guest Resources Hotel',
            'slug' => 'guest-resources',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'guest-resources.serena.test']);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
    ]);
    $user->assignRole('receptionist');

    $domain = 'guest-resources.serena.test';

    $this->actingAs($user)
        ->get("http://{$domain}/settings/resources/guests")
        ->assertForbidden();
});
