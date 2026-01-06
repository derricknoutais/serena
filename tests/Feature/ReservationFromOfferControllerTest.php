<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Offer;
use App\Models\Reservation;
use Database\Seeders\PermissionSeeder;

require_once __DIR__.'/FolioTestHelpers.php';
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

it('creates reservation from offer with room type id set', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'roomType' => $roomType,
        'guest' => $guest,
        'user' => $user,
        'room' => $room,
    ] = setupReservationEnvironment('offer-api');

    $user->givePermissionTo('frontdesk.view');

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => '24 Heures',
        'kind' => 'full_day',
        'time_rule' => 'rolling',
        'time_config' => [
            'duration_minutes' => 1440,
        ],
    ]);

    $domain = tenantDomain($tenant);

    /** @var TestResponse $response */
    $response = actingAs($user)
        ->postJson(sprintf('http://%s/frontdesk/reservations/from-offer', $domain), [
            'offer_id' => $offer->id,
            'room_id' => $room->id,
            'guest_id' => $guest->id,
            'code' => 'RSV-TEST-001',
            'unit_price' => 10000,
            'base_amount' => 10000,
            'tax_amount' => 0,
            'total_amount' => 10000,
        ]);

    $response->assertSuccessful();

    $reservationId = $response->json('reservation.id');

    /** @var Reservation $reservation */
    $reservation = Reservation::query()->findOrFail($reservationId);

    expect($reservation->room_type_id)->toBe($roomType->id);

    $activity = Activity::query()
        ->where('log_name', 'reservation')
        ->where('subject_id', (string) $reservation->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['reservation_code'])->toBe('RSV-TEST-001');
    expect($activity->properties['offer_name'])->toBe('24 Heures');
    expect($activity->properties['room_number'] ?? null)->not->toBeNull();
}
);
