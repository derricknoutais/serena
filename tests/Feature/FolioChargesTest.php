<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Services\FolioBillingService;
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

it('allows a permitted user to edit a folio item', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('folio-charges-edit');

    $user->assignRole('owner');

    $billing = app(FolioBillingService::class);
    $folio = $billing->ensureMainFolioForReservation($reservation);

    $item = $folio->addCharge([
        'description' => 'Bar',
        'quantity' => 1,
        'unit_price' => 10000,
        'tax_amount' => 0,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/folios/%s/items/%s',
        tenantDomain($tenant),
        $folio->id,
        $item->id,
    ), [
        'description' => 'Bar modifié',
        'quantity' => 2,
        'unit_price' => 5000,
        'tax_amount' => 0,
        'discount_percent' => 0,
        'discount_amount' => 0,
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();

    $item->refresh();
    expect($item->description)->toBe('Bar modifié');
    expect($item->quantity)->toBe(2.0);
    expect($folio->fresh()->charges_total)->toBe($item->total_amount);
});

it('allows a permitted user to delete a folio item', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('folio-charges-delete');

    $user->assignRole('owner');

    $billing = app(FolioBillingService::class);
    $folio = $billing->ensureMainFolioForReservation($reservation);

    $item = $folio->addCharge([
        'description' => 'Petit-déjeuner',
        'quantity' => 1,
        'unit_price' => 8000,
        'tax_amount' => 0,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)->delete(sprintf(
        'http://%s/folios/%s/items/%s',
        tenantDomain($tenant),
        $folio->id,
        $item->id,
    ), [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertOk();

    expect($item->fresh()->trashed())->toBeTrue();
    expect($folio->fresh()->charges_total)->toBe(0.0);
});

it('denies editing a folio item when the user lacks the permission', function (): void {
    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('folio-charges-deny');

    $user->givePermissionTo('frontdesk.view');

    $billing = app(FolioBillingService::class);
    $folio = $billing->ensureMainFolioForReservation($reservation);

    $item = $folio->addCharge([
        'description' => 'Room service',
        'quantity' => 1,
        'unit_price' => 12000,
        'tax_amount' => 0,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/folios/%s/items/%s',
        tenantDomain($tenant),
        $folio->id,
        $item->id,
    ), [
        'description' => 'Room service modifié',
        'quantity' => 1,
        'unit_price' => 12000,
        'tax_amount' => 0,
        'discount_percent' => 0,
        'discount_amount' => 0,
    ], [
        'Accept' => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertForbidden();
});
