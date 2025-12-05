<?php

require_once __DIR__.'/FolioTestHelpers.php';

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

    $response->assertSessionHasErrors('status');
    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_PENDING);
});
