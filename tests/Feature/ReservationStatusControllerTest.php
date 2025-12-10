<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\CashSession;
use App\Models\Reservation;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);
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
