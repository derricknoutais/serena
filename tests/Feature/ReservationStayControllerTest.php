<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Offer;
use App\Services\FolioBillingService;
use App\Services\ReservationAvailabilityService;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);
});

it('honors the provided checkout time when updating stay dates', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('stay-datetime');

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Deluxe Nuitée',
        'kind' => 'night',
        'check_in_from' => '14:00',
        'check_out_until' => '11:30',
        'billing_mode' => 'per_stay',
        'is_active' => true,
    ]);

    $reservation->update([
        'offer_id' => $offer->id,
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
        'check_in_date' => '2025-05-01 15:00:00',
        'check_out_date' => '2025-05-05 15:00:00',
    ]);

    $this->mock(ReservationAvailabilityService::class)
        ->shouldReceive('ensureAvailable')
        ->once()
        ->andReturnTrue();

    $this->mock(FolioBillingService::class)
        ->shouldReceive('addStayAdjustment')
        ->once();

    $newCheckout = '2025-05-03T11:30:00';

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/stay/dates',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'check_out_date' => $newCheckout,
    ]);

    $response->assertOk();

    expect($reservation->fresh()->check_out_date?->format('Y-m-d H:i'))
        ->toBe('2025-05-03 11:30');
});
