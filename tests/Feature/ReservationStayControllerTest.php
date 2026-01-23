<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Folio;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Notifications\GenericPushNotification;
use App\Services\FolioBillingService;
use App\Services\ReservationAvailabilityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

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
        'time_rule' => 'fixed_window',
        'time_config' => [
            'start_time' => '14:00',
            'end_time' => '11:30',
        ],
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

    $billingMock = $this->mock(FolioBillingService::class);
    $billingMock->shouldReceive('addStayAdjustment')
        ->once();
    $billingMock->shouldReceive('syncStayChargeFromReservation')
        ->once()
        ->andReturn(\Mockery::mock(Folio::class));

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

it('forbids extending a stay without the extend permission', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('stay-permission');

    $user->givePermissionTo(Permission::findByName('frontdesk.view'));

    $newCheckout = $reservation->check_out_date?->copy()->addDay()->toDateTimeString()
        ?? now()->addDays(2)->toDateTimeString();

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/stay/dates',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'check_out_date' => $newCheckout,
    ]);

    $response->assertForbidden();
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

    $billingMock = $this->mock(FolioBillingService::class);
    $billingMock->shouldReceive('addStayExtensionItem')
        ->once();
    $billingMock->shouldNotReceive('syncStayChargeFromReservation');

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

it('charges weekend offers per configured night bundle when extending a stay', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
        'hotel' => $hotel,
        'roomType' => $roomType,
    ] = setupReservationEnvironment('stay-weekend-pack');

    $user->assignRole('owner');
    Notification::fake();

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Week-end',
        'kind' => 'weekend',
        'billing_mode' => 'per_stay',
        'time_rule' => 'weekend_window',
        'time_config' => [
            'checkout' => [
                'max_days_after_checkin' => 3,
            ],
        ],
        'is_active' => true,
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'offer_id' => $offer->id,
        'room_type_id' => $roomType->id,
        'currency' => 'XAF',
        'price' => 15000,
    ]);

    $reservation->update([
        'offer_id' => $offer->id,
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
        'check_in_date' => '2025-05-01 12:00:00',
        'check_out_date' => '2025-05-04 12:00:00',
        'unit_price' => 15000,
        'base_amount' => 15000,
        'total_amount' => 15000,
    ]);

    $this->mock(ReservationAvailabilityService::class)
        ->shouldReceive('ensureAvailable')
        ->once()
        ->andReturnTrue();

    $billingMock = $this->mock(FolioBillingService::class);
    $billingMock->shouldReceive('addStayExtensionItem')
        ->once();
    $billingMock->shouldNotReceive('syncStayChargeFromReservation');

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/stay/dates',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'check_out_date' => '2025-05-07T12:00:00',
    ]);

    $response->assertOk();

    expect($reservation->fresh()->base_amount)->toBe(30000);

    $reservation->loadMissing('room');

    Notification::assertSentTo($user, GenericPushNotification::class, function (GenericPushNotification $notification) use ($reservation): bool {
        return $notification->title === 'Prolongation de séjour'
            && str_contains($notification->body, $reservation->code ?? '')
            && str_contains($notification->body, $reservation->room?->number ?? '');
    });
});

it('charges package offers per configured night bundle when extending a stay', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
        'hotel' => $hotel,
        'roomType' => $roomType,
    ] = setupReservationEnvironment('stay-package-bundle');

    $user->assignRole('owner');

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Package 3 nuits',
        'kind' => 'package',
        'billing_mode' => 'per_stay',
        'time_rule' => 'weekend_window',
        'time_config' => [
            'checkout' => [
                'max_days_after_checkin' => 3,
            ],
        ],
        'is_active' => true,
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'offer_id' => $offer->id,
        'room_type_id' => $roomType->id,
        'currency' => 'XAF',
        'price' => 50000,
    ]);

    $reservation->update([
        'offer_id' => $offer->id,
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
        'check_in_date' => '2025-05-01 12:00:00',
        'check_out_date' => '2025-05-04 12:00:00',
        'unit_price' => 50000,
        'base_amount' => 50000,
        'total_amount' => 50000,
    ]);

    $this->mock(ReservationAvailabilityService::class)
        ->shouldReceive('ensureAvailable')
        ->once()
        ->andReturnTrue();

    $billingMock = $this->mock(FolioBillingService::class);
    $billingMock->shouldReceive('addStayExtensionItem')
        ->once();
    $billingMock->shouldNotReceive('syncStayChargeFromReservation');

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/stay/dates',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'check_out_date' => '2025-05-07T12:00:00',
    ]);

    $response->assertOk();

    expect($reservation->fresh()->base_amount)->toBe(100000);
});

it('calculates package extension quantities from the current checkout date', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
        'hotel' => $hotel,
        'roomType' => $roomType,
    ] = setupReservationEnvironment('stay-package-extension');

    $user->assignRole('owner');

    $nightOffer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Nuitée',
        'kind' => 'night',
        'billing_mode' => 'per_stay',
        'is_active' => true,
    ]);

    $packageOffer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Package 2 nuits',
        'kind' => 'package',
        'billing_mode' => 'per_stay',
        'time_rule' => 'weekend_window',
        'time_config' => [
            'checkout' => [
                'max_days_after_checkin' => 2,
            ],
        ],
        'is_active' => true,
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'offer_id' => $nightOffer->id,
        'room_type_id' => $roomType->id,
        'currency' => 'XAF',
        'price' => 10000,
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'offer_id' => $packageOffer->id,
        'room_type_id' => $roomType->id,
        'currency' => 'XAF',
        'price' => 35000,
    ]);

    $reservation->update([
        'offer_id' => $nightOffer->id,
        'offer_name' => $nightOffer->name,
        'offer_kind' => $nightOffer->kind,
        'check_in_date' => '2025-05-01 12:00:00',
        'check_out_date' => '2025-05-02 12:00:00',
        'unit_price' => 10000,
        'base_amount' => 10000,
        'total_amount' => 10000,
    ]);

    $this->mock(ReservationAvailabilityService::class)
        ->shouldReceive('ensureAvailable')
        ->once()
        ->andReturnTrue();

    $billingMock = $this->mock(FolioBillingService::class);
    $billingMock->shouldReceive('addStayExtensionItem')
        ->once()
        ->withArgs(function (Reservation $reservationArg, float $amount, Carbon $previousCheckOut, Carbon $newCheckOut, array $context) use ($reservation, $packageOffer): bool {
            expect($reservationArg->id)->toBe($reservation->id);
            expect($amount)->toBe(35000.0);
            expect($previousCheckOut->format('Y-m-d'))->toBe('2025-05-02');
            expect($newCheckOut->format('Y-m-d'))->toBe('2025-05-04');
            expect($context['offer_id'])->toBe($packageOffer->id);
            expect($context['offer_kind'])->toBe($packageOffer->kind);
            expect($context['meta']['previous_check_out'])->toBe('2025-05-02');
            expect($context['meta']['new_check_out'])->toBe('2025-05-04');
            expect($context['meta']['type_of_offer'])->toBe('package');
            expect($context['meta']['period'])->toBe('2025-05-02 - 2025-05-04');

            return true;
        });
    $billingMock->shouldNotReceive('syncStayChargeFromReservation');

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/stay/dates',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'check_out_date' => '2025-05-04T12:00:00',
        'offer_id' => $packageOffer->id,
    ]);

    $response->assertOk();

    $freshReservation = $reservation->fresh();
    expect($freshReservation->offer_id)->toBe($nightOffer->id);
    expect($freshReservation->base_amount)->toBe(45000);
});

it('charges fixed billing offers as a single unit when extending', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
        'hotel' => $hotel,
        'roomType' => $roomType,
    ] = setupReservationEnvironment('stay-fixed-extension');

    $user->assignRole('owner');

    $baseOffer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Nuitée',
        'kind' => 'night',
        'billing_mode' => 'per_night',
        'is_active' => true,
    ]);

    $fixedOffer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Week-end H48',
        'kind' => 'weekend',
        'billing_mode' => 'fixed',
        'time_rule' => 'weekend_window',
        'time_config' => [
            'checkout' => [
                'max_days_after_checkin' => 2,
            ],
        ],
        'is_active' => true,
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'offer_id' => $baseOffer->id,
        'room_type_id' => $roomType->id,
        'currency' => 'XAF',
        'price' => 10000,
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'offer_id' => $fixedOffer->id,
        'room_type_id' => $roomType->id,
        'currency' => 'XAF',
        'price' => 55000,
    ]);

    $reservation->update([
        'offer_id' => $baseOffer->id,
        'offer_name' => $baseOffer->name,
        'offer_kind' => $baseOffer->kind,
        'check_in_date' => '2025-05-01 12:00:00',
        'check_out_date' => '2025-05-02 12:00:00',
        'unit_price' => 10000,
        'base_amount' => 10000,
        'total_amount' => 10000,
    ]);

    $this->mock(ReservationAvailabilityService::class)
        ->shouldReceive('ensureAvailable')
        ->once()
        ->andReturnTrue();

    $billingMock = $this->mock(FolioBillingService::class);
    $billingMock->shouldReceive('addStayExtensionItem')
        ->once()
        ->withArgs(function (Reservation $reservationArg, float $amount, Carbon $previousCheckOut, Carbon $newCheckOut, array $context) use ($reservation, $fixedOffer): bool {
            expect($reservationArg->id)->toBe($reservation->id);
            expect($amount)->toBe(55000.0);
            expect($previousCheckOut->format('Y-m-d'))->toBe('2025-05-02');
            expect($newCheckOut->format('Y-m-d'))->toBe('2025-05-04');
            expect($context['offer_id'])->toBe($fixedOffer->id);
            expect($context['offer_kind'])->toBe($fixedOffer->kind);
            expect($context['meta']['period'])->toBe('2025-05-02 - 2025-05-04');

            return true;
        });
    $billingMock->shouldNotReceive('syncStayChargeFromReservation');

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/stay/dates',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'check_out_date' => '2025-05-04T12:00:00',
        'offer_id' => $fixedOffer->id,
    ]);

    $response->assertOk();

    $freshReservation = $reservation->fresh();
    expect($freshReservation->offer_id)->toBe($baseOffer->id);
    expect((float) $freshReservation->base_amount)->toBe(65000.0);
});

it('prices an extension with a selected offer without replacing the original', function (): void {
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
        'base_amount' => 15000,
        'total_amount' => 15000,
    ]);

    $this->mock(ReservationAvailabilityService::class)
        ->shouldReceive('ensureAvailable')
        ->once()
        ->andReturnTrue();

    $billingMock = $this->mock(FolioBillingService::class);
    $billingMock->shouldReceive('addStayExtensionItem')
        ->once()
        ->withArgs(function (Reservation $reservationArg, float $amount, Carbon $previousCheckOut, Carbon $newCheckOut, array $context) use ($reservation, $offerB): bool {
            expect($reservationArg->id)->toBe($reservation->id);
            expect($amount)->toBe(20000.0);
            expect($previousCheckOut->format('Y-m-d'))->toBe('2025-05-03');
            expect($newCheckOut->format('Y-m-d'))->toBe('2025-05-05');
            expect($context['offer_id'])->toBe($offerB->id);
            expect($context['offer_kind'])->toBe($offerB->kind);
            expect($context['meta']['previous_check_out'])->toBe('2025-05-03');
            expect($context['meta']['new_check_out'])->toBe('2025-05-05');
            expect($context['meta']['type_of_offer'])->toBe('night');
            expect($context['meta']['period'])->toBe('2025-05-03 - 2025-05-05');

            return true;
        });
    $billingMock->shouldNotReceive('syncStayChargeFromReservation');

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
    expect($freshReservation->offer_id)->toBe($offerA->id);
    expect($freshReservation->offer_kind)->toBe('weekend');
    expect($freshReservation->base_amount)->toBe(35000);
});
