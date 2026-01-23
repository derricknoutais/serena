<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\CashSession;
use App\Models\FolioItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\FolioBillingService;
use App\Services\NotificationRecipientResolver;
use App\Services\VapidEventNotifier;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('app.url', 'http://serena.test');
    Config::set('app.url_host', 'serena.test');
    Config::set('app.url_scheme', 'http');
    Config::set('tenancy.central_domains', ['serena.test']);

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('stores a payment via json and returns updated totals', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('cashflow');

    $user->assignRole('owner');

    CashSession::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'type' => 'frontdesk',
        'opened_by_user_id' => $user->id,
        'started_at' => now(),
        'starting_amount' => 0,
        'currency' => $hotel->currency ?? 'XAF',
        'status' => 'open',
        'business_date' => now()->toDateString(),
    ]);

    $this->mock(VapidEventNotifier::class)
        ->shouldReceive('notifyOwnersAndManagers')
        ->once()
        ->withArgs(function (
            string $eventKey,
            string $tenantId,
            ?int $hotelId,
            string $title,
            string $body,
            string $url,
            ?string $tag,
        ) use ($tenant, $hotel, $reservation): bool {
            return $eventKey === 'payment.recorded'
                && $tenantId === (string) $tenant->id
                && $hotelId === $hotel->id
                && $title === 'Paiement enregistré'
                && str_contains($body, '4 000')
                && str_contains($body, $hotel->currency ?? 'XAF')
                && str_contains($url, "/reservations/{$reservation->id}/folio")
                && $tag === 'payment-recorded';
        });

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

    $recipients = app(NotificationRecipientResolver::class)
        ->resolveByRoles(['owner', 'manager'], (string) $tenant->id, $hotel->id);

    expect($recipients)->not->toBeEmpty();
});

it('soft deletes a payment and refreshes totals', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('deletepay');

    $user->assignRole('owner');

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

it('updates a payment via json when permitted', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('updatepay');

    $user->assignRole('owner');

    CashSession::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'type' => 'frontdesk',
        'opened_by_user_id' => $user->id,
        'started_at' => now(),
        'starting_amount' => 0,
        'currency' => $hotel->currency ?? 'XAF',
        'status' => 'open',
        'business_date' => now()->toDateString(),
    ]);

    $billingService = app(FolioBillingService::class);
    $folio = $billingService->ensureMainFolioForReservation($reservation);

    $methodA = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Carte',
        'code' => 'CARD',
        'type' => 'card',
        'is_active' => true,
    ]);

    $methodB = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Mobile money',
        'code' => 'MOMO',
        'type' => 'mobile',
        'is_active' => true,
    ]);

    $payment = $folio->addPayment([
        'amount' => 5000,
        'currency' => 'XAF',
        'payment_method_id' => $methodA->id,
        'created_by_user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->patchJson(sprintf(
        'http://%s/folios/%s/payments/%s',
        tenantDomain($tenant),
        $folio->id,
        $payment->id,
    ), [
        'amount' => 7000,
        'payment_method_id' => $methodB->id,
        'note' => 'Ajustement',
    ]);

    $response->assertOk();

    $payment->refresh();
    expect($payment->amount)->toBe(7000.0);
    expect((int) $payment->payment_method_id)->toBe((int) $methodB->id);
    expect($payment->notes)->toBe('Ajustement');
});

it('forbids updating a payment without the edit permission', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('updatepay-deny');

    $user->givePermissionTo('payments.create');

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

    $response = $this->actingAs($user)->patchJson(sprintf(
        'http://%s/folios/%s/payments/%s',
        tenantDomain($tenant),
        $folio->id,
        $payment->id,
    ), [
        'amount' => 7000,
        'payment_method_id' => $method->id,
        'note' => 'Ajustement',
    ]);

    $response->assertForbidden();
});

it('generates an invoice from a folio and exposes the pdf view', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('invoicepdf');

    $user->assignRole('owner');

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
