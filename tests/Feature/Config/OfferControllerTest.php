<?php

require_once __DIR__.'/../FolioTestHelpers.php';

use App\Models\Hotel;
use App\Models\Offer;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function setupOfferTenant(): array
{
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Tenant Offers',
        'slug' => 'offers-tenant',
        'plan' => 'standard',
    ]);

    $tenant->createDomain(['domain' => 'offers-tenant.serena.test']);

    tenancy()->initialize($tenant);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hotel Offers',
        'code' => 'HOF1',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'address' => 'Main street',
        'city' => 'Douala',
        'country' => 'CM',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'active_hotel_id' => $hotel->id,
        'email' => 'offers-user@example.com',
        'email_verified_at' => now(),
    ]);

    $user->hotels()->attach($hotel);

    return compact('tenant', 'hotel', 'user');
}

it('stores weekend window configuration for offers', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupOfferTenant();

    $user->assignRole('owner');

    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $payload = [
        'name' => 'Offre weekend',
        'kind' => 'night',
        'billing_mode' => 'per_night',
        'time_rule' => 'weekend_window',
        'time_config' => [
            'checkin' => [
                'allowed_weekdays' => [1, 2],
                'start_time' => '12:00',
            ],
            'checkout' => [
                'time' => '12:00',
                'max_days_after_checkin' => 2,
            ],
        ],
        'valid_from' => now()->toDateString(),
        'valid_to' => now()->addWeek()->toDateString(),
        'description' => 'Offre valide lundi et mardi',
        'is_active' => true,
        'prices' => [],
    ];

    $response = actingAs($user)->post(sprintf(
        'http://%s/settings/resources/offers',
        tenantDomain($tenant),
    ), $payload);

    $response->assertRedirect();

    $offer = Offer::query()
        ->where('tenant_id', $tenant->id)
        ->where('hotel_id', $hotel->id)
        ->where('name', 'Offre weekend')
        ->firstOrFail();

    expect($offer->time_rule)->toBe('weekend_window');
    expect($offer->time_config['checkin']['allowed_weekdays'] ?? [])
        ->toBeArray()
        ->toEqual([1, 2]);
    expect($offer->time_config['checkout']['max_days_after_checkin'] ?? null)->toBe(2);
});

it('updates the rule and configuration when editing an offer', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupOfferTenant();

    $user->assignRole('owner');

    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Offre à modifier',
        'kind' => 'night',
        'billing_mode' => 'per_night',
        'time_rule' => 'rolling',
        'time_config' => ['duration_minutes' => 120],
        'is_active' => true,
    ]);

    $payload = [
        'name' => 'Offre à modifier',
        'kind' => 'night',
        'billing_mode' => 'per_night',
        'time_rule' => 'fixed_window',
        'time_config' => [
            'start_time' => '22:00',
            'end_time' => '08:00',
            'late_checkout' => null,
        ],
        'is_active' => true,
    ];

    $response = actingAs($user)->put(sprintf(
        'http://%s/settings/resources/offers/%s',
        tenantDomain($tenant),
        $offer->id,
    ), $payload);

    $response->assertRedirect();

    expect($offer->refresh()->time_rule)->toBe('fixed_window');
    expect($offer->time_config['start_time'])->toBe('22:00');
    expect($offer->time_config['end_time'])->toBe('08:00');
});
