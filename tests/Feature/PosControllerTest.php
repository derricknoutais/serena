<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Reservation;
use App\Models\Tax;
use App\Services\FolioBillingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

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

it('renders the POS page with products and reservations', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('pos-index');

    $user->assignRole('receptionist');
    $reservation->update(['status' => Reservation::STATUS_IN_HOUSE]);

    $category = ProductCategory::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Bar',
        'description' => 'Cocktails',
        'is_active' => true,
    ]);

    $tax = Tax::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'TVA',
        'rate' => 19.25,
        'type' => 'percentage',
        'is_city_tax' => false,
        'is_active' => true,
    ]);

    Product::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'product_category_id' => $category->id,
        'name' => 'Gin Tonic',
        'sku' => 'GIN-001',
        'unit_price' => 5000,
        'tax_id' => $tax->id,
        'account_code' => '7010',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(sprintf(
        'http://%s/pos',
        tenantDomain($tenant),
    ));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Index')
            ->has('categories', 1)
            ->has('products', 1)
            ->has('paymentMethods', 1)
            ->has('inHouseReservations', 1),
        );
});

it('creates a folio and payment for a counter sale', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupReservationEnvironment('pos-counter');

    $user->assignRole('receptionist');

    $category = ProductCategory::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Snacks',
        'description' => null,
        'is_active' => true,
    ]);

    $tax = Tax::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Taxe',
        'rate' => 10,
        'type' => 'percentage',
        'is_city_tax' => false,
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'product_category_id' => $category->id,
        'name' => 'Planche Mixte',
        'sku' => 'PL-01',
        'unit_price' => 8000,
        'tax_id' => $tax->id,
        'account_code' => '7070',
        'is_active' => true,
    ]);

    $method = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Mobile Money',
        'code' => 'MOMO',
        'type' => 'mobile',
        'is_active' => true,
    ]);

    $payload = [
        'items' => [
            [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => 2,
                'unit_price' => 8000,
                'tax_amount' => 1600,
                'total_amount' => 17600,
            ],
        ],
        'payment_method_id' => $method->id,
        'client_label' => 'Client bar',
    ];

    $response = $this->actingAs($user)->postJson(sprintf(
        'http://%s/pos/sales/counter',
        tenantDomain($tenant),
    ), $payload);

    $response->assertCreated()->assertJsonPath('success', true);

    expect(Folio::query()->count())->toBe(1);
    expect(FolioItem::query()->count())->toBe(1);
    expect(Payment::query()->count())->toBe(1);

    $folio = Folio::query()->first();
    expect($folio?->type)->toBe('pos');
    expect($folio?->payments()->value('amount'))->toBe(17600.0);
});

it('adds folio charges for a room sale', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('pos-room');

    $user->assignRole('receptionist');
    $reservation->update(['status' => Reservation::STATUS_IN_HOUSE]);

    $category = ProductCategory::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Mini-bar',
        'description' => null,
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'product_category_id' => $category->id,
        'name' => 'Bouteille d’eau',
        'sku' => 'WAT-01',
        'unit_price' => 1500,
        'tax_id' => null,
        'account_code' => '7080',
        'is_active' => true,
    ]);

    $billing = app(FolioBillingService::class);
    $folio = $billing->ensureMainFolioForReservation($reservation);

    $payload = [
        'reservation_id' => $reservation->id,
        'items' => [
            [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => 3,
                'unit_price' => 1500,
                'tax_amount' => 0,
                'total_amount' => 4500,
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson(sprintf(
        'http://%s/pos/sales/room',
        tenantDomain($tenant),
    ), $payload);

    $response->assertOk()->assertJsonPath('folio_id', $folio->id);

    expect($folio->fresh()->items()->count())->toBe(1);
    expect($folio->fresh()->charges_total)->toBeGreaterThanOrEqual(4500.0);
});
