<?php

declare(strict_types=1);

use App\Models\Offer;
use App\Models\Reservation;

require_once __DIR__.'/FolioTestHelpers.php';
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

it('creates reservation from offer with room type id set', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'roomType' => $roomType,
        'guest' => $guest,
        'user' => $user,
        'room' => $room,
    ] = setupReservationEnvironment('offer-api');

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => '24 Heures',
        'kind' => 'full_day',
        'valid_from' => now()->toDateString(),
        'valid_to' => now()->copy()->addDay()->toDateString(),
        'check_in_from' => null,
        'check_out_until' => null,
        'fixed_duration_hours' => null,
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
}
);
