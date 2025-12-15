<?php

use App\Models\Guest;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => [],
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Test Tenant',
        'slug' => 'serena',
        'contact_email' => 'tenant@example.com',
        'plan' => 'standard',
    ]);

    $tenant->createDomain(['domain' => 'serena.test']);

    tenancy()->initialize($tenant);

    $this->tenant = $tenant;
});

test('guests are redirected from activity page', function (): void {
    $response = $this->get('http://serena.test/activity');

    $response->assertRedirect();
});

test('authenticated users can view activity page with filters', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    Role::findOrCreate('owner');
    $user->assignRole('owner');
    $this->actingAs($user);

    $response = $this->get('http://serena.test/activity?event=confirmed&search=test');

    $response->assertOk()->assertInertia(function (Assert $page): void {
        $page->component('activity/Index')
            ->has('activities')
            ->has('filters', fn (Assert $filters) => $filters
                ->where('event', 'confirmed')
                ->where('search', 'test')
                ->etc()
            )
            ->has('users')
            ->has('events');
    });
});

test('activity page shows readable names for related ids', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    Role::findOrCreate('owner');
    $user->assignRole('owner');
    $this->actingAs($user);

    $hotelId = 1;
    \App\Models\Hotel::query()->firstOrCreate([
        'id' => $hotelId,
    ], [
        'tenant_id' => $this->tenant->id,
        'name' => 'Hotel Serena',
        'code' => 'SER',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'address' => 'Main St',
        'city' => 'Douala',
        'country' => 'CM',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $offer = Offer::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $hotelId,
        'name' => 'Offre Test',
        'kind' => 'full_day',
        'time_rule' => 'rolling',
        'time_config' => ['duration_minutes' => 60],
    ]);

    $roomType = RoomType::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $hotelId,
        'name' => 'Deluxe',
        'code' => 'DLX',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 10000,
        'description' => 'Room type',
    ]);

    $room = Room::query()->create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $hotelId,
        'room_type_id' => $roomType->id,
        'number' => '201',
        'floor' => '2',
        'status' => 'active',
        'hk_status' => 'clean',
    ]);

    $guest = Guest::query()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Test',
        'last_name' => 'Guest',
        'email' => 'test-guest@example.com',
    ]);

    $reservation = Reservation::query()->create([
        'tenant_id' => $this->tenant->id,
        'hotel_id' => $hotelId,
        'offer_id' => $offer->id,
        'room_id' => $room->id,
        'room_type_id' => $roomType->id,
        'guest_id' => $guest->id,
        'code' => 'RES-NAMES',
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => now(),
        'check_out_date' => now()->addDay(),
        'currency' => 'XAF',
        'unit_price' => 10000,
        'base_amount' => 10000,
        'tax_amount' => 0,
        'total_amount' => 10000,
    ]);

    activity('reservation')
        ->performedOn($reservation)
        ->causedBy($user)
        ->withProperties([
            'reservation_code' => $reservation->code,
            'room_id' => $reservation->room_id,
            'offer_id' => $offer->id,
            'guest_id' => $reservation->guest_id,
            'to_status' => $reservation->status,
        ])
        ->event('created')
        ->log('created');

    $response = $this->get('http://serena.test/activity');

    $response->assertOk()->assertInertia(function (Assert $page) use ($offer): void {
        $props = $page->toArray()['props'];
        $readable = $props['activities']['data'][0]['readable_properties'] ?? [];
        $offerTag = collect($readable)->firstWhere('label', 'Offre');

        expect($offerTag['value'] ?? null)->toBe($offer->name);
    });
});
