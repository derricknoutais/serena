<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\CashSession;
use App\Models\Offer;
use App\Models\Reservation;
use App\Services\FolioBillingService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

it('confirms a reservation when transition is allowed', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
    ] = setupReservationEnvironment('status-confirm');

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

    $reservation->update([
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => now()->toDateString(),
        'check_out_date' => now()->addDay()->toDateString(),
        'offer_id' => $offer->id,
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
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
        ->and($stayItem?->meta['offer_name'] ?? null)->toBe('Offre Test');
});

it('requires an open frontdesk cash session when applying a cancellation penalty', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
        'reservation' => $reservation,
    ] = setupReservationEnvironment('status-penalty');

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

it('blocks check-out when the folio has an outstanding balance', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
    ] = setupReservationEnvironment('status-checkout-balance');

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

it('allows managers to check out even when the folio has a balance', function (): void {
    [
        'tenant' => $tenant,
        'user' => $user,
        'reservation' => $reservation,
    ] = setupReservationEnvironment('status-checkout-manager');

    $guard = config('auth.defaults.guard', 'web');
    Role::query()->firstOrCreate([
        'name' => 'manager',
        'guard_name' => $guard,
    ]);

    $user->assignRole('manager');

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
