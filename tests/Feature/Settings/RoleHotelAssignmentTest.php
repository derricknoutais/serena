<?php

use App\Models\Hotel;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('owner can assign a hotel to a user', function () {
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

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Main Hotel',
    ]);
    $secondaryHotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Second Hotel',
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'email' => 'owner@test-hotel.test',
    ]);
    $owner->assignRole('owner');

    $member = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'email' => 'member@test-hotel.test',
    ]);

    $domain = 'test-hotel.serena.test';

    $response = $this->actingAs($owner)
        ->patch("http://{$domain}/users/{$member->id}/role", [
            'role' => 'manager',
            'hotel_ids' => [$hotel->id, $secondaryHotel->id],
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('hotel_user', [
        'hotel_id' => $hotel->id,
        'user_id' => $member->id,
    ]);
    $this->assertDatabaseHas('hotel_user', [
        'hotel_id' => $secondaryHotel->id,
        'user_id' => $member->id,
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $member->id,
        'active_hotel_id' => $hotel->id,
    ]);
});

test('roles settings only lists hotels for the current tenant', function () {
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

    $hotelA = Hotel::query()->create([
        'tenant_id' => $tenantA->getKey(),
        'name' => 'Alpha Plaza',
    ]);

    $hotelB = Hotel::query()->create([
        'tenant_id' => $tenantB->getKey(),
        'name' => 'Beta Suites',
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $tenantA->getKey(),
        'email' => 'owner@alpha.test',
    ]);
    $owner->assignRole('owner');

    $this->actingAs($owner)
        ->get('http://alpha.serena.test/settings/roles')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($hotelA, $hotelB): void {
            $hotels = collect($page->toArray()['props']['hotels'] ?? []);

            expect($hotels->pluck('id'))->toContain($hotelA->id);
            expect($hotels->pluck('id'))->not->toContain($hotelB->id);
        });
});

test('hotel queries are scoped to the current tenant', function () {
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

    Hotel::query()->create([
        'tenant_id' => $tenantA->getKey(),
        'name' => 'Alpha Plaza',
    ]);

    Hotel::query()->create([
        'tenant_id' => $tenantB->getKey(),
        'name' => 'Beta Suites',
    ]);

    tenancy()->initialize($tenantA);

    $hotels = Hotel::query()->pluck('name')->all();

    expect($hotels)->toContain('Alpha Plaza');
    expect($hotels)->not->toContain('Beta Suites');
});
