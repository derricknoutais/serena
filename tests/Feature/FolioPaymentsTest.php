<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\FolioItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\FolioBillingService;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('app.url', 'http://serena.test');
    Config::set('app.url_host', 'serena.test');
    Config::set('app.url_scheme', 'http');
    Config::set('tenancy.central_domains', ['serena.test']);
});

it('stores a payment via json and returns updated totals', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('cashflow');

    $billingService = app(FolioBillingService::class);
    $folio = $billingService->ensureMainFolioForReservation($reservation);

    FolioItem::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'folio_id' => $folio->id,
        'description' => 'Test charge',
        'quantity' => 1,
        'unit_price' => 10000,
        'base_amount' => 10000,
        'tax_amount' => 0,
        'total_amount' => 10000,
    ]);

    $method = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Mobile money',
        'code' => 'MOMO',
        'type' => 'mobile',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->postJson(sprintf(
        'http://%s/folios/%s/payments',
        tenantDomain($tenant),
        $folio->id,
    ), [
        'amount' => 4000,
        'payment_method_id' => $method->id,
        'note' => 'Acompte',
    ]);

    $response->assertOk();

    $payload = $response->json();

    expect($payload['totals']['payments'])->toBe(4000);
    expect($payload['payments'])->toHaveCount(1);
    expect(Payment::query()->count())->toBe(1);
});

it('soft deletes a payment and refreshes totals', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('deletepay');

    $billingService = app(FolioBillingService::class);
    $folio = $billingService->ensureMainFolioForReservation($reservation);

    $method = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Carte',
        'code' => 'CARD',
        'type' => 'card',
        'is_active' => true,
    ]);

    $payment = $folio->addPayment([
        'amount' => 5000,
        'currency' => 'XAF',
        'payment_method_id' => $method->id,
        'created_by_user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->deleteJson(sprintf(
        'http://%s/folios/%s/payments/%s',
        tenantDomain($tenant),
        $folio->id,
        $payment->id,
    ));

    $response->assertOk();
    expect(Payment::withTrashed()->count())->toBe(1);
    expect(Payment::query()->count())->toBe(0);
});

it('generates an invoice from a folio and exposes the pdf view', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('invoicepdf');

    $billingService = app(FolioBillingService::class);
    $folio = $billingService->ensureMainFolioForReservation($reservation);

    FolioItem::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'folio_id' => $folio->id,
        'description' => 'Nuitée',
        'quantity' => 1,
        'unit_price' => 10000,
        'base_amount' => 10000,
        'tax_amount' => 1500,
        'total_amount' => 11500,
    ]);

    $generateResponse = $this->actingAs($user)->postJson(sprintf(
        'http://%s/folios/%s/invoices',
        tenantDomain($tenant),
        $folio->id,
    ));

    $generateResponse->assertOk();
    $invoiceId = $generateResponse->json('invoice.id');

    expect(Invoice::query()->count())->toBe(1);

    $pdfResponse = $this->actingAs($user)->get(sprintf(
        'http://%s/invoices/%s/print',
        tenantDomain($tenant),
        $invoiceId,
    ));

    $pdfResponse->assertOk()->assertSee('Facture');
});
