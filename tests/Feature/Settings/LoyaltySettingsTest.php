<?php

require_once __DIR__.'/../FolioTestHelpers.php';

use App\Models\HotelLoyaltySetting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('renders loyalty settings for managers', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupReservationEnvironment('loyalty-settings');

    $user->assignRole('manager');

    $this->actingAs($user)
        ->get(sprintf('http://%s/settings/loyalty', tenantDomain($tenant)))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Loyalty')
            ->where('hotel.id', $hotel->id)
            ->where('settings.enabled', false)
        );
});

it('updates loyalty settings for the active hotel', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupReservationEnvironment('loyalty-settings-update');

    $user->assignRole('owner');

    $this->actingAs($user)
        ->put(sprintf('http://%s/settings/loyalty', tenantDomain($tenant)), [
            'enabled' => true,
            'earning_mode' => 'amount',
            'points_per_amount' => 2,
            'amount_base' => 1000,
            'points_per_night' => null,
            'fixed_points' => null,
            'max_points_per_stay' => 200,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('hotel_loyalty_settings', [
        'tenant_id' => $hotel->tenant_id,
        'hotel_id' => $hotel->id,
        'enabled' => true,
        'earning_mode' => 'amount',
        'points_per_amount' => 2,
        'amount_base' => 1000,
        'max_points_per_stay' => 200,
    ]);
});

it('blocks loyalty settings without permission', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('loyalty-settings-blocked');

    $user->assignRole('receptionist');

    $this->actingAs($user)
        ->get(sprintf('http://%s/settings/loyalty', tenantDomain($tenant)))
        ->assertForbidden();

    $this->actingAs($user)
        ->put(sprintf('http://%s/settings/loyalty', tenantDomain($tenant)), [
            'enabled' => true,
            'earning_mode' => 'fixed',
            'fixed_points' => 50,
        ])
        ->assertForbidden();

    expect(HotelLoyaltySetting::query()->count())->toBe(0);
});
