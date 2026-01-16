<?php

require_once __DIR__.'/../FolioTestHelpers.php';

use App\Models\FolioItem;
use App\Models\Guest;
use App\Services\FolioBillingService;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);
});

it('changes the reservation guest when permitted', function (): void {
    ['tenant' => $tenant, 'hotel' => $hotel, 'user' => $user, 'reservation' => $reservation] = setupReservationEnvironment('frontdesk-guest');

    Permission::query()->firstOrCreate(['name' => 'frontdesk.view']);
    Permission::query()->firstOrCreate(['name' => 'reservations.change_guest']);

    $user->givePermissionTo(['frontdesk.view', 'reservations.change_guest']);

    $newGuest = Guest::query()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.doe@example.com',
        'phone' => '987654321',
    ]);

    $response = $this->actingAs($user)->patch(sprintf(
        'http://%s/reservations/%s/guest',
        tenantDomain($tenant),
        $reservation->id,
    ), [
        'guest_id' => $newGuest->id,
    ]);

    $response->assertOk();

    expect($reservation->fresh()->guest_id)->toBe($newGuest->id);
});

it('adds a folio adjustment line when permitted', function (): void {
    ['tenant' => $tenant, 'hotel' => $hotel, 'user' => $user, 'reservation' => $reservation] = setupReservationEnvironment('frontdesk-adjust');

    Permission::query()->firstOrCreate(['name' => 'frontdesk.view']);
    Permission::query()->firstOrCreate(['name' => 'folios.adjust']);

    $user->givePermissionTo(['frontdesk.view', 'folios.adjust']);

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);

    $response = $this->actingAs($user)->post(sprintf(
        'http://%s/folios/%s/adjustment',
        tenantDomain($tenant),
        $folio->id,
    ), [
        'amount' => -500,
        'reason' => 'Correction test',
    ]);

    $response->assertOk();

    $item = FolioItem::query()
        ->where('folio_id', $folio->id)
        ->where('type', 'adjustment')
        ->latest('id')
        ->first();

    expect($item)->not->toBeNull();
    expect((float) $item->total_amount)->toBe(-500.0);
});
