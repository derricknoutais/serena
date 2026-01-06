<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Folio;
use App\Models\Hotel;
use App\Models\PaymentMethod;
use App\Services\FolioBillingService;
use Database\Seeders\PermissionSeeder;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    $this->seed(PermissionSeeder::class);
});

it('returns a folio payload for the reservation endpoint', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('orion');

    $user->givePermissionTo('frontdesk.view');

    $otherHotel = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Secondary Hotel',
        'code' => 'H2O',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'address' => 'Main street',
        'city' => 'Douala',
        'country' => 'CM',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $globalMethod = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => null,
        'name' => 'Cash',
        'code' => 'CASH',
        'type' => 'cash',
        'is_active' => true,
    ]);

    $hotelMethod = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Mobile Money',
        'code' => 'MOMO',
        'type' => 'mobile',
        'is_active' => true,
    ]);

    $otherHotelMethod = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $otherHotel->id,
        'name' => 'Card',
        'code' => 'CARD',
        'type' => 'card',
        'is_active' => true,
    ]);

    PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Legacy',
        'code' => 'LEG',
        'type' => 'cash',
        'is_active' => false,
    ]);

    $billingService = app(FolioBillingService::class);
    $folio = $billingService->ensureMainFolioForReservation($reservation);

    $folio->addCharge([
        'description' => 'Nuitée',
        'quantity' => 1,
        'unit_price' => 10000,
        'tax_amount' => 1900,
        'total_amount' => 11900,
        'date' => now()->toDateString(),
    ]);

    $folio->addPayment([
        'amount' => 5000,
        'currency' => 'XAF',
        'payment_method_id' => $hotelMethod->id,
        'reference' => 'PMT-1',
        'notes' => 'Acompte',
        'created_by_user_id' => $user->id,
    ]);

    $billingService->generateInvoiceFromFolio($folio);

    $domain = tenantDomain($tenant);

    $response = $this->actingAs($user)->getJson(sprintf(
        'http://%s/reservations/%s/folio',
        $domain,
        $reservation->id,
    ));

    $response
        ->assertOk()
        ->assertJsonPath('folio.code', $folio->code)
        ->assertJsonPath('reservation.id', $reservation->id)
        ->assertJsonPath('payments.0.payment_method.id', $hotelMethod->id);

    $data = $response->json();

    expect($data['items'])->toHaveCount(1);
    expect($data['invoices'])->not->toBeEmpty();

    $methodIds = collect($data['paymentMethods'])->pluck('id');

    expect($methodIds)->toContain($globalMethod->id);
    expect($methodIds)->toContain($hotelMethod->id);
    expect($methodIds)->not->toContain($otherHotelMethod->id);

    expect(
        Folio::query()
            ->where('reservation_id', $reservation->id)
            ->count()
    )->toBe(1);
});

it('returns json data when folio show is requested via JSON', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('lyra');

    $user->givePermissionTo('frontdesk.view');

    $billingService = app(FolioBillingService::class);
    $folio = $billingService->ensureMainFolioForReservation($reservation);

    $method = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Carte',
        'code' => 'CARTE',
        'type' => 'card',
        'is_active' => true,
    ]);

    $folio->addCharge([
        'description' => 'Petit-déjeuner',
        'quantity' => 1,
        'unit_price' => 7000,
        'tax_amount' => 0,
        'total_amount' => 7000,
        'date' => now()->toDateString(),
    ]);

    $folio->addPayment([
        'amount' => 3000,
        'currency' => 'XAF',
        'payment_method_id' => $method->id,
        'reference' => 'PMT-2',
        'created_by_user_id' => $user->id,
    ]);

    $billingService->generateInvoiceFromFolio($folio);

    $response = $this->actingAs($user)->getJson(sprintf(
        'http://%s/folios/%s',
        tenantDomain($tenant),
        $folio->id,
    ));

    $response
        ->assertOk()
        ->assertJsonPath('folio.id', $folio->id)
        ->assertJsonPath('reservation.code', $reservation->code)
        ->assertJsonPath('payments.0.method.id', $method->id);
});
