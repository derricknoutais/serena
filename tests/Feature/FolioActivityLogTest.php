<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Activity;
use App\Models\CashSession;
use App\Models\Folio;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => [],
    ]);

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('logs activity when a folio charge is added', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'guest' => $guest,
        'user' => $user,
    ] = setupReservationEnvironment('folio-charge-log');

    $user->assignRole('owner');

    $folio = Folio::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'reservation_id' => $reservation->id,
        'guest_id' => $guest->id,
        'code' => 'FOL-CHG',
        'status' => 'open',
        'is_main' => true,
        'type' => 'stay',
        'currency' => 'XAF',
    ]);

    actingAs($user);

    post(sprintf('http://%s/folios/%s/items', tenantDomain($tenant), $folio->id), [
        'description' => 'Extra bed',
        'quantity' => 1,
        'unit_price' => 5000,
        'tax_amount' => 0,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'date' => Carbon::now()->toDateString(),
    ])->assertRedirect();

    $activity = Activity::query()
        ->where('log_name', 'folio')
        ->where('subject_id', (string) $folio->id)
        ->where('event', 'item_created')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['description'])->toBe('Extra bed');
});

it('logs activity when a payment is voided', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'guest' => $guest,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('payment-void-log');

    $user->assignRole('owner');

    $folio = Folio::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'reservation_id' => $reservation->id,
        'guest_id' => $guest->id,
        'code' => 'FOL-PAY',
        'status' => 'open',
        'is_main' => true,
        'type' => 'stay',
        'currency' => 'XAF',
    ]);

    actingAs($user);

    CashSession::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'opened_by_user_id' => $user->id,
        'type' => 'frontdesk',
        'started_at' => now(),
        'starting_amount' => 0,
        'status' => 'open',
    ]);

    post(sprintf('http://%s/folios/%s/payments', tenantDomain($tenant), $folio->id), [
        'amount' => 2000,
        'currency' => 'XAF',
        'payment_method_id' => $methods[0]->id,
        'paid_at' => Carbon::now()->toDateString(),
    ])->assertRedirect();

    $payment = $folio->refresh()->payments()->latest()->first();
    expect($payment)->not->toBeNull();

    delete(sprintf('http://%s/folios/%s/payments/%s', tenantDomain($tenant), $folio->id, $payment->id))
        ->assertRedirect();

    $activity = Activity::query()
        ->where('log_name', 'payment')
        ->where('subject_id', (string) $payment->id)
        ->where('event', 'voided')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect((float) $activity->properties['amount'])->toBe(2000.0);
});
