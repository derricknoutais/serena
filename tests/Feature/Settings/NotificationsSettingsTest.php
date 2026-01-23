<?php

require_once __DIR__.'/../FolioTestHelpers.php';

use App\Models\NotificationPreference;
use App\Support\NotificationEventCatalog;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('renders notification settings for managers', function (): void {
    $this->seed([
        RoleSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('notifications-settings');

    $user->assignRole('manager');

    $this->actingAs($user)
        ->get(sprintf('http://%s/settings/notifications', tenantDomain($tenant)))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Notifications')
            ->has('events')
        );
});

it('updates notification settings for the active hotel', function (): void {
    $this->seed([
        RoleSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupReservationEnvironment('notifications-settings-update');

    $user->assignRole('owner');

    $payload = [
        'events' => [
            'payment.recorded' => [
                'roles' => ['owner', 'manager'],
                'channels' => [NotificationEventCatalog::CHANNEL_PUSH],
            ],
        ],
    ];

    $this->actingAs($user)
        ->put(sprintf('http://%s/settings/notifications', tenantDomain($tenant)), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('notification_preferences', [
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'event_key' => 'payment.recorded',
    ]);

    $preference = NotificationPreference::query()->first();
    expect($preference)->not->toBeNull()
        ->and($preference?->roles)->toBe(['owner', 'manager'])
        ->and($preference?->channels)->toBe([NotificationEventCatalog::CHANNEL_PUSH]);
});

it('blocks notification settings for non managers', function (): void {
    $this->seed([
        RoleSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'user' => $user,
    ] = setupReservationEnvironment('notifications-settings-blocked');

    $user->assignRole('receptionist');

    $this->actingAs($user)
        ->get(sprintf('http://%s/settings/notifications', tenantDomain($tenant)))
        ->assertForbidden();

    $this->actingAs($user)
        ->put(sprintf('http://%s/settings/notifications', tenantDomain($tenant)), [
            'events' => [
                'payment.recorded' => [
                    'roles' => ['owner'],
                    'channels' => [NotificationEventCatalog::CHANNEL_PUSH],
                ],
            ],
        ])
        ->assertForbidden();

    expect(NotificationPreference::query()->count())->toBe(0);
});
