<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\CashSession;
use App\Models\Offer;
use App\Models\Reservation;
use App\Services\FolioBillingService;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $guard = config('auth.defaults.guard', 'web');
    $permissions = [
        'frontdesk.view',
        'housekeeping.view',
        'analytics.view',
        'reservations.confirm',
        'reservations.check_in',
        'reservations.check_out',
        'reservations.check_out_with_balance',
        'reservations.override_datetime',
        'reservations.extend_stay',
        'reservations.shorten_stay',
        'reservations.change_room',
        'reservations.cancel',
        'reservations.force_status',
        'payments.create',
        'folio_items.void',
        'housekeeping.mark_inspected',
        'housekeeping.mark_clean',
        'housekeeping.mark_dirty',
        'cash_sessions.view',
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

if (! function_exists('grantFrontdeskAccess')) {
    function grantFrontdeskAccess($user): void
    {
        $user->givePermissionTo('frontdesk.view');
        $user->givePermissionTo('reservations.confirm');
        $user->givePermissionTo('reservations.check_in');
        $user->givePermissionTo('reservations.check_out');
        $user->givePermissionTo('reservations.cancel');
        $user->givePermissionTo('reservations.force_status');
    }
}

it('confirms a reservation when transition is allowed', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
    ] = setupReservationEnvironment('status-confirm');

    grantFrontdeskAccess($user);

    $reservation->update([
        'status' => Reservation::STATUS_PENDING,
        'check_in_date' => '2025-04-01',
        'check_out_date' => '2025-04-02',
    ]);

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/status',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'confirm',
    ]);

    $response->assertRedirect();

    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_CONFIRMED);
});

it('rejects an invalid status transition', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
    ] = setupReservationEnvironment('status-invalid');

    grantFrontdeskAccess($user);

    $reservation->update([
        'status' => Reservation::STATUS_PENDING,
        'check_in_date' => '2025-05-01',
        'check_out_date' => '2025-05-02',
    ]);

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/status',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'check_out',
    ]);

    $response->assertSessionHasErrors('status');
    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_PENDING);
});

it('creates the stay folio item on check-in', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('status-checkin');

    grantFrontdeskAccess($user);

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Offre Test',
        'kind' => 'night',
        'billing_mode' => 'per_stay',
        'time_rule' => 'rolling',
        'time_config' => ['duration_minutes' => 1440],
        'is_active' => true,
    ]);

    \App\Models\OfferRoomTypePrice::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $reservation->room_type_id,
        'offer_id' => $offer->id,
        'currency' => 'XAF',
        'price' => 18000,
    ]);

    $reservation->update([
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDay()->toDateString(),
        'offer_id' => $offer->id,
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
        'unit_price' => 10000,
    ]);

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/status',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'check_in',
        'actual_check_in_at' => now()->setTime(15, 0)->toDateTimeString(),
    ]);

    $response->assertRedirect();

    $reservation->refresh();
    $folio = $reservation->mainFolio()->first();
    $stayItem = $folio?->items()->where('is_stay_item', true)->first();

    expect($folio)->not->toBeNull()
        ->and($stayItem)->not->toBeNull()
        ->and($stayItem?->type)->toBe('stay')
        ->and($stayItem?->meta['offer_id'] ?? null)->toBe($offer->id)
        ->and($stayItem?->meta['offer_name'] ?? null)->toBe('Offre Test')
        ->and($stayItem?->unit_price)->toBe(18000.0);
});

it('requires an open frontdesk cash session when applying a cancellation penalty', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
        'reservation' => $reservation,
    ] = setupReservationEnvironment('status-penalty');

    grantFrontdeskAccess($user);

    $reservation->update([
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-06-01',
        'check_out_date' => '2025-06-02',
    ]);

    $response = $this->actingAs($user)->from(sprintf(
        'http://%s/reservations',
        tenantDomain($tenant),
    ))->patch(sprintf(
        'http://%s/reservations/%s/status',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'cancel',
        'penalty_amount' => 5000,
    ]);

    $response->assertSessionHasErrors('penalty_amount');
    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_CONFIRMED);

    CashSession::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'opened_by_user_id' => $user->id,
        'type' => 'frontdesk',
        'started_at' => now(),
        'starting_amount' => 0,
        'status' => 'open',
    ]);

    $response = $this->actingAs($user)->from(sprintf(
        'http://%s/reservations',
        tenantDomain($tenant),
    ))->patch(sprintf(
        'http://%s/reservations/%s/status',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'cancel',
        'penalty_amount' => 5000,
    ]);

    $response->assertRedirect();
});

it('requires a payment method for early check-in fees', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
        'reservation' => $reservation,
    ] = setupReservationEnvironment('status-early-payment-method');

    grantFrontdeskAccess($user);

    $hotel->update([
        'stay_settings' => [
            'standard_checkin_time' => '14:00',
            'early_checkin' => [
                'policy' => 'paid',
                'fee_type' => 'flat',
                'fee_value' => 5000,
            ],
        ],
    ]);

    $reservation->update([
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-06-10 14:00:00',
        'check_out_date' => '2025-06-11 12:00:00',
        'base_amount' => 10000,
    ]);

    $response = $this->actingAs($user)->from(sprintf(
        'http://%s/reservations',
        tenantDomain($tenant),
    ))->patch(sprintf(
        'http://%s/reservations/%s/status',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'check_in',
        'actual_check_in_at' => '2025-06-10 09:00:00',
    ]);

    $response->assertSessionHasErrors('early_payment_method_id');
    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_CONFIRMED);
});

it('requires an open frontdesk cash session for late checkout fees', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
        'reservation' => $reservation,
        'methods' => $methods,
    ] = setupReservationEnvironment('status-late-cash-session');

    grantFrontdeskAccess($user);

    $hotel->update([
        'stay_settings' => [
            'standard_checkout_time' => '12:00',
            'late_checkout' => [
                'policy' => 'paid',
                'fee_type' => 'flat',
                'fee_value' => 3000,
            ],
        ],
    ]);

    $reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2025-06-10 14:00:00',
        'check_out_date' => '2025-06-11 12:00:00',
        'base_amount' => 10000,
    ]);

    $response = $this->actingAs($user)->from(sprintf(
        'http://%s/reservations',
        tenantDomain($tenant),
    ))->patch(sprintf(
        'http://%s/reservations/%s/status',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'check_out',
        'actual_check_out_at' => '2025-06-11 14:30:00',
        'late_payment_method_id' => $methods[0]->id,
    ]);

    $response->assertSessionHasErrors('cash_session');
    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_IN_HOUSE);
});

it('records early check-in payments in the frontdesk cash session', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
        'reservation' => $reservation,
        'methods' => $methods,
    ] = setupReservationEnvironment('status-early-payment');

    grantFrontdeskAccess($user);

    $hotel->update([
        'stay_settings' => [
            'standard_checkin_time' => '14:00',
            'early_checkin' => [
                'policy' => 'paid',
                'fee_type' => 'flat',
                'fee_value' => 4500,
            ],
        ],
    ]);

    $reservation->update([
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-06-10 14:00:00',
        'check_out_date' => '2025-06-11 12:00:00',
        'base_amount' => 10000,
    ]);

    $session = CashSession::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'opened_by_user_id' => $user->id,
        'type' => 'frontdesk',
        'started_at' => now(),
        'starting_amount' => 0,
        'status' => 'open',
    ]);

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/status',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'check_in',
        'actual_check_in_at' => '2025-06-10 09:00:00',
        'early_payment_method_id' => $methods[0]->id,
    ]);

    $response->assertRedirect();

    $reservation->refresh();
    $folio = $reservation->mainFolio()->first();
    $payment = $folio?->payments()->latest('id')->first();

    expect($reservation->status)->toBe(Reservation::STATUS_IN_HOUSE)
        ->and($payment)->not->toBeNull()
        ->and($payment?->amount)->toBe(4500.0)
        ->and($payment?->cash_session_id)->toBe($session->id);
});

it('blocks check-out when the folio has an outstanding balance', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
    ] = setupReservationEnvironment('status-checkout-balance');

    grantFrontdeskAccess($user);

    $reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDay()->toDateString(),
    ]);

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);
    $folio->addCharge([
        'description' => 'Room balance',
        'quantity' => 1,
        'unit_price' => 15000,
        'tax_amount' => 0,
        'type' => 'stay',
    ]);

    $response = $this->actingAs($user)->from(sprintf(
        'http://%s/reservations',
        tenantDomain($tenant),
    ))->patch(sprintf(
        'http://%s/reservations/%s/status',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'check_out',
    ]);

    $response->assertSessionHasErrors('check_out');
    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_IN_HOUSE);
});

it('allows checkout with balance when permission is granted', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
    ] = setupReservationEnvironment('status-checkout-manager');

    grantFrontdeskAccess($user);
    $user->givePermissionTo('reservations.check_out_with_balance');

    $reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDay()->toDateString(),
    ]);

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);
    $folio->addCharge([
        'description' => 'Room balance',
        'quantity' => 1,
        'unit_price' => 15000,
        'tax_amount' => 0,
        'type' => 'stay',
    ]);

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/status',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'check_out',
    ]);

    $response->assertRedirect();
    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_CHECKED_OUT);
});

it('does not block check-out previews with early check-in rules', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('status-checkout-preview');

    grantFrontdeskAccess($user);

    $hotel->update([
        'stay_settings' => [
            'standard_checkin_time' => '14:00',
            'early_checkin' => [
                'policy' => 'forbidden',
                'cutoff_time' => '12:00',
            ],
        ],
    ]);

    $reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2025-12-25 08:30:00',
        'check_out_date' => '2025-12-25 11:30:00',
        'actual_check_in_at' => '2025-12-25 08:30:00',
    ]);

    $response = $this->actingAs($user)->post(sprintf(
        'http://%s/reservations/%s/stay-adjustments/preview',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'check_out',
        'actual_datetime' => '2025-12-25 11:30:00',
    ]);

    $response->assertOk();
    $response->assertJsonPath('early.is_early_checkin', false);
    $response->assertJsonPath('early.blocked', false);
});

it('uses offer late checkout policy when configured', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('status-checkout-offer-late');

    grantFrontdeskAccess($user);

    $hotel->update([
        'stay_settings' => [
            'standard_checkin_time' => '14:00',
            'standard_checkout_time' => '12:00',
            'late_checkout' => [
                'policy' => 'free',
            ],
        ],
    ]);

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Détente 3h',
        'kind' => 'hourly',
        'billing_mode' => 'fixed',
        'time_rule' => 'rolling',
        'time_config' => [
            'duration_minutes' => 180,
            'late_checkout' => [
                'policy' => 'paid',
                'grace_minutes' => 15,
                'fee_type' => 'flat',
                'fee_value' => 5000,
            ],
        ],
        'is_active' => true,
    ]);

    $reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'offer_id' => $offer->id,
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
        'check_in_date' => '2025-12-25 14:15:00',
        'check_out_date' => '2025-12-25 17:15:00',
        'actual_check_in_at' => '2025-12-25 14:15:00',
        'base_amount' => 10000,
    ]);

    $response = $this->actingAs($user)->post(sprintf(
        'http://%s/reservations/%s/stay-adjustments/preview',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'check_out',
        'actual_datetime' => '2025-12-25 17:55:00',
    ]);

    $response->assertOk();
    $response->assertJsonPath('late.is_late_checkout', true);
    $response->assertJsonPath('late.fee', 5000);
    $response->assertJsonPath('late.policy', 'paid');
    $response->assertJsonPath('late.fee_type', 'flat');
    $response->assertJsonPath('late.fee_value', 5000);
    $response->assertJsonPath('late.minutes', 25);
    $response->assertJsonPath('late.expected_checkout_at', '2025-12-25 17:15:00');
    $response->assertJsonPath('late.actual_checkout_at', '2025-12-25 17:55:00');
});

it('calculates late checkout fee per hour for offer policy', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
        'hotel' => $hotel,
    ] = setupReservationEnvironment('status-checkout-offer-late-hour');

    grantFrontdeskAccess($user);

    $hotel->update([
        'stay_settings' => [
            'late_checkout' => [
                'policy' => 'free',
            ],
        ],
    ]);

    $offer = Offer::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Détente 3h',
        'kind' => 'hourly',
        'billing_mode' => 'fixed',
        'time_rule' => 'rolling',
        'time_config' => [
            'duration_minutes' => 180,
            'late_checkout' => [
                'policy' => 'paid',
                'grace_minutes' => 15,
                'fee_type' => 'per_hour',
                'fee_value' => 1000,
            ],
        ],
        'is_active' => true,
    ]);

    $reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'offer_id' => $offer->id,
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
        'check_in_date' => '2025-12-25 14:15:00',
        'check_out_date' => '2025-12-25 17:15:00',
        'actual_check_in_at' => '2025-12-25 14:15:00',
        'base_amount' => 10000,
    ]);

    $response = $this->actingAs($user)->post(sprintf(
        'http://%s/reservations/%s/stay-adjustments/preview',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'action' => 'check_out',
        'actual_datetime' => '2025-12-25 18:40:00',
    ]);

    $response->assertOk();
    $response->assertJsonPath('late.is_late_checkout', true);
    $response->assertJsonPath('late.fee', 2000);
    $response->assertJsonPath('late.policy', 'paid');
    $response->assertJsonPath('late.fee_type', 'per_hour');
    $response->assertJsonPath('late.fee_value', 1000);
    $response->assertJsonPath('late.minutes', 70);
    $response->assertJsonPath('late.expected_checkout_at', '2025-12-25 17:15:00');
    $response->assertJsonPath('late.actual_checkout_at', '2025-12-25 18:40:00');
});
