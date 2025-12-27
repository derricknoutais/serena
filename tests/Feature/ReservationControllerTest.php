<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Offer;
use App\Models\Reservation;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $guard = config('auth.defaults.guard', 'web');
    $permissions = [
        'reservations.override_datetime',
        'folio_items.void',
        'housekeeping.mark_inspected',
        'housekeeping.mark_clean',
        'housekeeping.mark_dirty',
        'cash_sessions.open',
        'cash_sessions.close',
        'rooms.view', 'rooms.create', 'rooms.update', 'rooms.delete',
        'room_types.view', 'room_types.create', 'room_types.update', 'room_types.delete',
        'offers.view', 'offers.create', 'offers.update', 'offers.delete',
        'products.view', 'products.create', 'products.update', 'products.delete',
        'product_categories.view', 'product_categories.create', 'product_categories.update', 'product_categories.delete',
        'taxes.view', 'taxes.create', 'taxes.update', 'taxes.delete',
        'payment_methods.view', 'payment_methods.create', 'payment_methods.update', 'payment_methods.delete',
        'maintenance_tickets.view', 'maintenance_tickets.create', 'maintenance_tickets.update', 'maintenance_tickets.close',
        'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
        'pos.view', 'pos.create',
        'night_audit.view', 'night_audit.export',
    ];

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => $guard,
        ]);
    }
});

function reservationPayload(array $overrides = []): array
{
    $base = [
        'code' => 'RSV-TEST-001',
        'check_in_date' => now()->addDays(10)->toDateString(),
        'check_out_date' => now()->addDays(11)->toDateString(),
        'currency' => 'XAF',
        'unit_price' => 10000,
        'base_amount' => 10000,
        'tax_amount' => 0,
        'total_amount' => 10000,
        'adults' => 1,
        'children' => 0,
        'notes' => null,
    ];

    return array_merge($base, $overrides);
}

it('rejects forbidden status on reservation creation', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'guest' => $guest,
        'roomType' => $roomType,
    ] = setupReservationEnvironment('reservation-create-status');

    $payload = reservationPayload([
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'status' => Reservation::STATUS_IN_HOUSE,
    ]);

    $response = actingAs($user)->post(sprintf(
        'http://%s/reservations',
        tenantDomain($tenant),
    ), $payload);

    $response->assertSessionHasErrors('status');

    expect(Reservation::query()->where('code', $payload['code'])->exists())->toBeFalse();
});

it('allows pending or confirmed status on reservation creation', function (string $status): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'guest' => $guest,
        'roomType' => $roomType,
    ] = setupReservationEnvironment('reservation-create-ok');

    $payload = reservationPayload([
        'code' => sprintf('RSV-TEST-%s', $status),
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'status' => $status,
    ]);

    $response = actingAs($user)->post(sprintf(
        'http://%s/reservations',
        tenantDomain($tenant),
    ), $payload);

    $response->assertRedirect();

    $reservation = Reservation::query()->where('code', $payload['code'])->firstOrFail();

    expect($reservation->status)->toBe($status);
})->with([
    'pending' => Reservation::STATUS_PENDING,
    'confirmed' => Reservation::STATUS_CONFIRMED,
]);

it('allows hourly offers to pass midnight', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'guest' => $guest,
        'roomType' => $roomType,
        'room' => $room,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('reservation-hourly-midnight');

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Détente 3h',
        'kind' => 'hourly',
        'fixed_duration_hours' => 3,
        'billing_mode' => 'per_stay',
        'check_in_from' => '14:00',
        'check_out_until' => '01:00',
        'is_active' => true,
    ]);

    $payload = reservationPayload([
        'code' => 'RSV-TEST-HOURLY',
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'room_id' => $room->id,
        'offer_id' => $offer->id,
        'check_in_date' => '2025-05-01 22:00:00',
        'check_out_date' => '2025-05-02 01:00:00',
    ]);

    $response = actingAs($user)->post(sprintf(
        'http://%s/reservations',
        tenantDomain($tenant),
    ), $payload);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $reservation = Reservation::query()->where('code', 'RSV-TEST-HOURLY')->first();

    expect($reservation)->not->toBeNull();
    expect($reservation->offer_kind)->toBe('hourly');
});

it('prevents status changes via reservation update', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
        'guest' => $guest,
        'roomType' => $roomType,
    ] = setupReservationEnvironment('reservation-update-status');

    $reservation->update([
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => now()->addDays(5)->toDateString(),
        'check_out_date' => now()->addDays(6)->toDateString(),
    ]);

    $payload = reservationPayload([
        'code' => $reservation->code,
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'status' => Reservation::STATUS_CANCELLED,
    ]);

    $response = actingAs($user)->put(sprintf(
        'http://%s/reservations/%s',
        tenantDomain($tenant),
        $reservation->id,
    ), $payload);

    $response->assertSessionHasErrors('status');

    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_CONFIRMED);
});
