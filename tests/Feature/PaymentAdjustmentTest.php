<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\HotelDayClosure;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\FolioBillingService;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

it('denies void and refund without permission', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('payment-adjust-deny');

    $user->assignRole('receptionist');

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);

    $this->actingAs($user);

    $payment = $folio->addPayment([
        'amount' => 10000,
        'currency' => $folio->currency,
        'payment_method_id' => $methods[0]->id,
        'paid_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    $domain = tenantDomain($tenant);

    $this->post(sprintf(
        'http://%s/payments/%s/void',
        $domain,
        $payment->id,
    ), [
        'reason' => 'Test',
    ])->assertForbidden();

    $this->post(sprintf(
        'http://%s/payments/%s/refund',
        $domain,
        $payment->id,
    ), [
        'amount' => 100,
        'payment_method_id' => $methods[0]->id,
        'reason' => 'Test',
    ])->assertForbidden();
});

it('voids a payment and stores metadata', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'reservation' => $reservation,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('payment-adjust-void');

    $user->assignRole('owner');

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);

    $this->actingAs($user);

    $payment = $folio->addPayment([
        'amount' => 15000,
        'currency' => $folio->currency,
        'payment_method_id' => $methods[0]->id,
        'paid_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    $domain = tenantDomain($tenant);

    $this->post(sprintf(
        'http://%s/payments/%s/void',
        $domain,
        $payment->id,
    ), [
        'reason' => 'Erreur encaissement',
    ])->assertOk();

    $voided = Payment::withTrashed()->find($payment->id);

    expect($voided)
        ->not->toBeNull()
        ->and($voided->voided_at)->not->toBeNull()
        ->and($voided->voided_by_user_id)->toBe($user->id)
        ->and($voided->void_reason)->toBe('Erreur encaissement')
        ->and($voided->trashed())->toBeTrue();
});

it('creates a refund payment linked to its parent', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('payment-adjust-refund');

    $user->assignRole('owner');

    $cardMethod = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Carte',
        'code' => 'CARD',
        'type' => 'card',
        'is_active' => true,
    ]);

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);

    $this->actingAs($user);

    $payment = $folio->addPayment([
        'amount' => 20000,
        'currency' => $folio->currency,
        'payment_method_id' => $methods[0]->id,
        'paid_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    $domain = tenantDomain($tenant);

    $this->post(sprintf(
        'http://%s/payments/%s/refund',
        $domain,
        $payment->id,
    ), [
        'amount' => 5000,
        'payment_method_id' => $cardMethod->id,
        'reason' => 'Erreur montant',
        'refund_reference' => 'RF-001',
    ])->assertOk();

    $refund = Payment::query()->where('parent_payment_id', $payment->id)->first();

    expect($refund)
        ->not->toBeNull()
        ->and($refund->entry_type)->toBe(Payment::ENTRY_TYPE_REFUND)
        ->and($refund->amount)->toBe(-5000.0)
        ->and($refund->refund_reason)->toBe('Erreur montant')
        ->and($refund->refund_reference)->toBe('RF-001')
        ->and($refund->parent_payment_id)->toBe($payment->id);
});

it('prevents refunding more than remaining', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('payment-adjust-limit');

    $user->assignRole('owner');

    $cardMethod = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Carte',
        'code' => 'CARD',
        'type' => 'card',
        'is_active' => true,
    ]);

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);

    $this->actingAs($user);

    $payment = $folio->addPayment([
        'amount' => 10000,
        'currency' => $folio->currency,
        'payment_method_id' => $methods[0]->id,
        'paid_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    $domain = tenantDomain($tenant);

    $this->post(sprintf(
        'http://%s/payments/%s/refund',
        $domain,
        $payment->id,
    ), [
        'amount' => 6000,
        'payment_method_id' => $cardMethod->id,
    ])->assertOk();

    $this->postJson(sprintf(
        'http://%s/payments/%s/refund',
        $domain,
        $payment->id,
    ), [
        'amount' => 5000,
        'payment_method_id' => $cardMethod->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['amount']);
});

it('prevents voiding a refunded payment', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('payment-adjust-void-after');

    $user->assignRole('owner');

    $cardMethod = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Carte',
        'code' => 'CARD',
        'type' => 'card',
        'is_active' => true,
    ]);

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);

    $this->actingAs($user);

    $payment = $folio->addPayment([
        'amount' => 12000,
        'currency' => $folio->currency,
        'payment_method_id' => $methods[0]->id,
        'paid_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    $domain = tenantDomain($tenant);

    $this->post(sprintf(
        'http://%s/payments/%s/refund',
        $domain,
        $payment->id,
    ), [
        'amount' => 2000,
        'payment_method_id' => $cardMethod->id,
    ])->assertOk();

    $this->postJson(sprintf(
        'http://%s/payments/%s/void',
        $domain,
        $payment->id,
    ), [
        'reason' => 'Test',
    ])->assertStatus(422);
});

it('denies void and refund when business day is closed', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    Carbon::setTestNow('2026-01-10 10:00:00');

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('payment-adjust-closed');

    $user->assignRole('owner');

    $folio = app(FolioBillingService::class)->ensureMainFolioForReservation($reservation);

    $this->actingAs($user);

    $payment = $folio->addPayment([
        'amount' => 9000,
        'currency' => $folio->currency,
        'payment_method_id' => $methods[0]->id,
        'paid_at' => now(),
        'created_by_user_id' => $user->id,
    ]);

    HotelDayClosure::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'business_date' => $payment->business_date?->toDateString(),
        'started_at' => now()->startOfDay(),
        'closed_at' => now(),
        'closed_by_user_id' => $user->id,
        'status' => HotelDayClosure::STATUS_CLOSED,
        'summary' => ['notes' => 'Clôture test'],
    ]);

    $domain = tenantDomain($tenant);

    $this->post(sprintf(
        'http://%s/payments/%s/void',
        $domain,
        $payment->id,
    ), [
        'reason' => 'Test',
    ])->assertForbidden();

    $this->post(sprintf(
        'http://%s/payments/%s/refund',
        $domain,
        $payment->id,
    ), [
        'amount' => 100,
        'payment_method_id' => $methods[0]->id,
    ])->assertForbidden();

    Carbon::setTestNow();
});
