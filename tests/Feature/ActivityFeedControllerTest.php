<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Activity;
use App\Models\Hotel;
use App\Models\Reservation;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);
});

it('returns activities scoped to the reservation with friendly dates', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'roomType' => $roomType,
        'room' => $room,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('activity-scope');

    $hotel->update(['timezone' => 'UTC']);

    actingAs($user);

    $reservationActivity = activity('reservation')
        ->performedOn($reservation)
        ->causedBy($user)
        ->withProperties([
            'reservation_code' => $reservation->code,
            'room_id' => $reservation->room_id,
        ])
        ->event('confirmed')
        ->log('confirmed');

    Activity::query()->whereKey($reservationActivity->id)->update([
        'created_at' => Carbon::create(2025, 12, 17, 9, 42, 38, 'UTC'),
        'updated_at' => Carbon::create(2025, 12, 17, 9, 42, 38, 'UTC'),
    ]);

    $otherHotel = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Other Hotel',
        'code' => 'OTH1',
        'currency' => 'XAF',
        'timezone' => 'UTC',
        'address' => 'Main street',
        'city' => 'Douala',
        'country' => 'CM',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    Activity::query()->create([
        'log_name' => 'reservation',
        'description' => 'wrong hotel',
        'tenant_id' => $tenant->id,
        'hotel_id' => $otherHotel->id,
        'subject_type' => Reservation::class,
        'subject_id' => (string) $reservation->id,
        'causer_type' => $user::class,
        'causer_id' => $user->id,
        'properties' => [
            'reservation_code' => 'RES-OTHER',
            'room_id' => $reservation->room_id,
        ],
        'event' => 'cancelled',
        'created_at' => Carbon::create(2025, 12, 18, 8, 0, 0, 'UTC'),
        'updated_at' => Carbon::create(2025, 12, 18, 8, 0, 0, 'UTC'),
    ]);

    Activity::query()->create([
        'log_name' => 'reservation',
        'description' => 'wrong code',
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'subject_type' => Reservation::class,
        'subject_id' => (string) $reservation->id,
        'causer_type' => $user::class,
        'causer_id' => $user->id,
        'properties' => [
            'reservation_code' => 'RES-WRONG',
            'room_id' => $reservation->room_id,
        ],
        'event' => 'checked_out',
        'created_at' => Carbon::create(2025, 12, 16, 8, 0, 0, 'UTC'),
        'updated_at' => Carbon::create(2025, 12, 16, 8, 0, 0, 'UTC'),
    ]);

    $response = getJson(sprintf(
        'http://%s/reservations/%s/activity',
        tenantDomain($tenant),
        $reservation->id,
    ));

    $response->assertOk();

    $payload = $response->json();

    expect($payload)->toHaveCount(1);
    expect($payload[0]['id'])->toBe($reservationActivity->id);
    expect($payload[0]['created_at'])->toBe('17/12/2025 09:42');
    expect($payload[0]['event'])->toBe('confirmed');
    expect($payload[0]['properties']['reservation_code'])->toBe($reservation->code);
});
