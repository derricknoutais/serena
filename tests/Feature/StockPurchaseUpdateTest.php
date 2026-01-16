<?php

use App\Models\Hotel;
use App\Models\StockItem;
use App\Models\StockPurchase;
use App\Models\StockPurchaseLine;
use App\Models\StorageLocation;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

it('updates a draft stock purchase', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Stock Hotel',
        'slug' => 'stock-hotel',
        'plan' => 'standard',
        'contact_email' => 'stock@hotel.test',
        'data' => [
            'name' => 'Stock Hotel',
            'slug' => 'stock-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'stock-hotel.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Stock Hotel',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $hotel->users()->syncWithoutDetaching([$user->getKey()]);

    $location = StorageLocation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Main Storage',
        'code' => 'MAIN',
        'category' => 'general',
        'is_active' => true,
    ]);

    $stockItem = StockItem::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Laundry Soap',
        'sku' => 'SOAP-1',
        'unit' => 'unit',
        'item_category' => 'maintenance',
        'is_active' => true,
        'default_purchase_price' => 100,
        'currency' => 'XAF',
    ]);

    $purchase = StockPurchase::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'storage_location_id' => $location->getKey(),
        'reference_no' => 'PO-100',
        'supplier_name' => 'Initial Supplier',
        'purchased_at' => now()->toDateString(),
        'status' => StockPurchase::STATUS_DRAFT,
        'subtotal_amount' => 100,
        'total_amount' => 100,
        'currency' => 'XAF',
        'created_by_user_id' => $user->getKey(),
    ]);

    StockPurchaseLine::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'stock_purchase_id' => $purchase->getKey(),
        'stock_item_id' => $stockItem->getKey(),
        'quantity' => 1,
        'unit_cost' => 100,
        'total_cost' => 100,
        'currency' => 'XAF',
        'notes' => null,
    ]);

    $payload = [
        'storage_location_id' => $location->getKey(),
        'reference_no' => 'PO-200',
        'supplier_name' => 'Updated Supplier',
        'purchased_at' => now()->subDay()->toDateString(),
        'currency' => 'XAF',
        'lines' => [
            [
                'stock_item_id' => $stockItem->getKey(),
                'quantity' => 2,
                'unit_cost' => 150,
                'currency' => 'XAF',
                'notes' => 'Urgent',
            ],
        ],
    ];

    $response = $this->actingAs($user)
        ->putJson("http://stock-hotel.serena.test/stock/purchases/{$purchase->getKey()}", $payload);

    $response->assertOk();

    $purchase->refresh();
    $line = StockPurchaseLine::query()->where('stock_purchase_id', $purchase->getKey())->first();

    expect((float) $purchase->total_amount)->toBe(300.0)
        ->and($purchase->reference_no)->toBe('PO-200')
        ->and($line)->not->toBeNull()
        ->and((float) $line->total_cost)->toBe(300.0)
        ->and($line->notes)->toBe('Urgent');
});

it('renders the stock purchases index with purchases', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Index Hotel',
        'slug' => 'index-hotel',
        'plan' => 'standard',
        'contact_email' => 'index@hotel.test',
        'data' => [
            'name' => 'Index Hotel',
            'slug' => 'index-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'index-hotel.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Index Hotel',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $hotel->users()->syncWithoutDetaching([$user->getKey()]);

    $location = StorageLocation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Main Storage',
        'code' => 'MAIN',
        'category' => 'general',
        'is_active' => true,
    ]);

    $stockItem = StockItem::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Index Item',
        'sku' => 'IDX-1',
        'unit' => 'unit',
        'item_category' => 'maintenance',
        'is_active' => true,
        'default_purchase_price' => 40,
        'currency' => 'XAF',
    ]);

    $purchase = StockPurchase::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'storage_location_id' => $location->getKey(),
        'reference_no' => 'PO-INDEX',
        'supplier_name' => 'Index Supplier',
        'purchased_at' => now()->toDateString(),
        'status' => StockPurchase::STATUS_DRAFT,
        'subtotal_amount' => 40,
        'total_amount' => 40,
        'currency' => 'XAF',
        'created_by_user_id' => $user->getKey(),
    ]);

    StockPurchaseLine::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'stock_purchase_id' => $purchase->getKey(),
        'stock_item_id' => $stockItem->getKey(),
        'quantity' => 1,
        'unit_cost' => 40,
        'total_cost' => 40,
        'currency' => 'XAF',
        'notes' => null,
    ]);

    $this->actingAs($user)
        ->get('http://index-hotel.serena.test/stock/purchases')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Stock/Purchases/Index')
            ->has('purchases', 1)
            ->where('purchases.0.id', $purchase->getKey()));
});

it('blocks updates when a stock purchase is received', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Received Hotel',
        'slug' => 'received-hotel',
        'plan' => 'standard',
        'contact_email' => 'received@hotel.test',
        'data' => [
            'name' => 'Received Hotel',
            'slug' => 'received-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'received-hotel.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Received Hotel',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $hotel->users()->syncWithoutDetaching([$user->getKey()]);

    $location = StorageLocation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Main Storage',
        'code' => 'MAIN',
        'category' => 'general',
        'is_active' => true,
    ]);

    $stockItem = StockItem::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Cleaner',
        'sku' => 'CLEAN-1',
        'unit' => 'unit',
        'item_category' => 'maintenance',
        'is_active' => true,
        'default_purchase_price' => 90,
        'currency' => 'XAF',
    ]);

    $purchase = StockPurchase::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'storage_location_id' => $location->getKey(),
        'reference_no' => 'PO-300',
        'supplier_name' => 'Supplier',
        'purchased_at' => now()->toDateString(),
        'status' => StockPurchase::STATUS_RECEIVED,
        'subtotal_amount' => 90,
        'total_amount' => 90,
        'currency' => 'XAF',
        'created_by_user_id' => $user->getKey(),
    ]);

    StockPurchaseLine::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'stock_purchase_id' => $purchase->getKey(),
        'stock_item_id' => $stockItem->getKey(),
        'quantity' => 1,
        'unit_cost' => 90,
        'total_cost' => 90,
        'currency' => 'XAF',
        'notes' => null,
    ]);

    $payload = [
        'storage_location_id' => $location->getKey(),
        'reference_no' => 'PO-301',
        'supplier_name' => 'Supplier Updated',
        'purchased_at' => now()->toDateString(),
        'currency' => 'XAF',
        'lines' => [
            [
                'stock_item_id' => $stockItem->getKey(),
                'quantity' => 2,
                'unit_cost' => 90,
                'currency' => 'XAF',
            ],
        ],
    ];

    $response = $this->actingAs($user)
        ->putJson("http://received-hotel.serena.test/stock/purchases/{$purchase->getKey()}", $payload);

    $response->assertConflict();
});
