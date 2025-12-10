<?php

require_once __DIR__.'/../FolioTestHelpers.php';

use App\Models\Hotel;
use App\Models\Offer;
use App\Models\Tenant;
use App\Models\User;
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

it('stores numeric valid_days_of_week for offers', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupOfferTenant();

    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $payload = [
        'name' => 'Offre semaine',
        'kind' => 'night',
        'billing_mode' => 'per_night',
        'fixed_duration_hours' => null,
        'check_in_from' => null,
        'check_out_until' => null,
        'valid_days_of_week' => [1, 2],
        'valid_from' => now()->toDateString(),
        'valid_to' => now()->addWeek()->toDateString(),
        'description' => 'Offre valide lundi et mardi',
        'is_active' => true,
        'prices' => [],
    ];

    $response = actingAs($user)->post(sprintf(
        'http://%s/ressources/offers',
        tenantDomain($tenant),
    ), $payload);

    $response->assertRedirect();

    $offer = Offer::query()
        ->where('tenant_id', $tenant->id)
        ->where('hotel_id', $hotel->id)
        ->where('name', 'Offre semaine')
        ->firstOrFail();

    expect($offer->valid_days_of_week)
        ->toBeArray()
        ->toEqual([1, 2]);
});
