<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Services\FolioBillingService;
use App\Services\ReservationAvailabilityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('honors the provided checkout time when updating stay dates', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('stay-datetime');

    $user->assignRole('owner');

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

it('rounds stay quantities up when checkout time adds a partial day', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('stay-rounding');

    $user->assignRole('owner');

    $reservation->update([
        'check_in_date' => '2025-05-01 10:00:00',
        'check_out_date' => '2025-05-02 10:00:00',
        'unit_price' => 10000,
        'base_amount' => 10000,
        'total_amount' => 10000,
    ]);

    $this->mock(ReservationAvailabilityService::class)
        ->shouldReceive('ensureAvailable')
        ->once()
        ->andReturnTrue();

    $this->mock(FolioBillingService::class)
        ->shouldReceive('addStayAdjustment')
        ->once();

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/stay/dates',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'check_out_date' => '2025-05-02T22:00:00',
    ]);

    $response->assertOk();

    expect($reservation->fresh()->base_amount)->toBe(20000);
});

it('allows changing the offer when extending a stay', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
        'hotel' => $hotel,
        'roomType' => $roomType,
    ] = setupReservationEnvironment('stay-offer');

    $user->assignRole('owner');

    $offerA = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Week-end',
        'kind' => 'weekend',
        'billing_mode' => 'per_stay',
        'is_active' => true,
    ]);

    $offerB = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Nuitée',
        'kind' => 'night',
        'billing_mode' => 'per_stay',
        'is_active' => true,
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'offer_id' => $offerA->id,
        'room_type_id' => $roomType->id,
        'currency' => 'XAF',
        'price' => 15000,
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'offer_id' => $offerB->id,
        'room_type_id' => $roomType->id,
        'currency' => 'XAF',
        'price' => 10000,
    ]);

    $reservation->update([
        'offer_id' => $offerA->id,
        'offer_name' => $offerA->name,
        'offer_kind' => $offerA->kind,
        'check_in_date' => '2025-05-01 12:00:00',
        'check_out_date' => '2025-05-03 12:00:00',
        'unit_price' => 15000,
        'base_amount' => 30000,
        'total_amount' => 30000,
    ]);

    $this->mock(ReservationAvailabilityService::class)
        ->shouldReceive('ensureAvailable')
        ->once()
        ->andReturnTrue();

    $this->mock(FolioBillingService::class)
        ->shouldReceive('addStayAdjustment')
        ->once();

    $newCheckout = '2025-05-05T12:00:00';

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/stay/dates',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'check_out_date' => $newCheckout,
        'offer_id' => $offerB->id,
    ]);

    $response->assertOk();

    $freshReservation = $reservation->fresh();
    expect($freshReservation->offer_id)->toBe($offerB->id);
    expect($freshReservation->offer_kind)->toBe('night');
    expect($freshReservation->unit_price)->toBe(10000);
});
