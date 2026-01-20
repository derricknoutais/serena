<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\BarOrder;
use App\Models\BarTable;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockOnHand;
use App\Models\StorageLocation;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;

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

it('consumes bar stock on counter sale', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('pos-stock-consume');

    $user->assignRole('manager');

    $location = StorageLocation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Bar',
        'category' => 'bar',
        'is_active' => true,
    ]);

    $hotel->forceFill(['default_bar_stock_location_id' => $location->id])->save();

    $stockItem = StockItem::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Bière',
        'unit' => 'PC',
        'item_category' => 'bar',
        'is_active' => true,
        'default_purchase_price' => 500,
        'currency' => 'XAF',
        'reorder_point' => 0,
        'is_kit' => false,
    ]);

    StockOnHand::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'storage_location_id' => $location->id,
        'stock_item_id' => $stockItem->id,
        'quantity_on_hand' => 10,
    ]);

    $category = ProductCategory::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Boissons',
        'is_active' => true,
    ]);

    $product = new Product([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'product_category_id' => $category->id,
        'name' => 'Bière',
        'unit_price' => 1000,
        'tax_id' => null,
        'is_active' => true,
        'manage_stock' => true,
        'stock_item_id' => $stockItem->id,
        'stock_quantity_per_unit' => 2,
    ]);
    $product->account_code = '';
    $product->save();

    CashSession::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'type' => 'bar',
        'status' => 'open',
        'started_at' => now(),
        'opened_by_user_id' => $user->id,
        'starting_amount' => 0,
    ]);

    $response = actingAs($user)->postJson(sprintf(
        'http://%s/pos/sales/counter',
        tenantDomain($tenant),
    ), [
        'items' => [
            [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => 2,
                'unit_price' => 1000,
                'tax_amount' => 0,
                'total_amount' => 2000,
            ],
        ],
        'payment_method_id' => $methods[0]->id,
        'client_label' => 'Client comptoir',
    ]);

    $response->assertSuccessful();

    $onHand = StockOnHand::query()->firstOrFail();
    expect((float) $onHand->quantity_on_hand)->toBe(6.0);

    $movement = StockMovement::query()->firstOrFail();
    expect($movement->movement_type)->toBe(StockMovement::TYPE_CONSUME);
    expect((float) $movement->lines()->first()->quantity)->toBe(4.0);
});

it('returns stock when a bar order is voided after consumption', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
        'methods' => $methods,
    ] = setupReservationEnvironment('pos-stock-void');

    $user->assignRole('manager');

    $location = StorageLocation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Bar',
        'category' => 'bar',
        'is_active' => true,
    ]);

    $hotel->forceFill(['default_bar_stock_location_id' => $location->id])->save();

    $stockItem = StockItem::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Soda',
        'unit' => 'PC',
        'item_category' => 'bar',
        'is_active' => true,
        'default_purchase_price' => 200,
        'currency' => 'XAF',
        'reorder_point' => 0,
        'is_kit' => false,
    ]);

    StockOnHand::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'storage_location_id' => $location->id,
        'stock_item_id' => $stockItem->id,
        'quantity_on_hand' => 5,
    ]);

    $category = ProductCategory::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Soft',
        'is_active' => true,
    ]);

    $product = new Product([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'product_category_id' => $category->id,
        'name' => 'Soda',
        'unit_price' => 500,
        'tax_id' => null,
        'is_active' => true,
        'manage_stock' => true,
        'stock_item_id' => $stockItem->id,
        'stock_quantity_per_unit' => 1,
    ]);
    $product->account_code = '';
    $product->save();

    $table = BarTable::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Table 1',
        'area' => 'Bar',
        'is_active' => true,
    ]);

    $order = BarOrder::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'bar_table_id' => $table->id,
        'status' => BarOrder::STATUS_OPEN,
        'opened_at' => now(),
        'cashier_user_id' => $user->id,
    ]);

    CashSession::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'type' => 'bar',
        'status' => 'open',
        'started_at' => now(),
        'opened_by_user_id' => $user->id,
        'starting_amount' => 0,
    ]);

    $saleResponse = actingAs($user)->postJson(sprintf(
        'http://%s/pos/sales/counter',
        tenantDomain($tenant),
    ), [
        'items' => [
            [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => 2,
                'unit_price' => 500,
                'tax_amount' => 0,
                'total_amount' => 1000,
            ],
        ],
        'payment_method_id' => $methods[0]->id,
        'client_label' => 'Table 1',
        'bar_order_id' => $order->id,
    ]);

    $saleResponse->assertSuccessful();

    $order->refresh();
    expect($order->stock_consumed_at)->not->toBeNull();

    $voidResponse = actingAs($user)->postJson(sprintf(
        'http://%s/bar/orders/%s/void',
        tenantDomain($tenant),
        $order->id,
    ));

    $voidResponse->assertSuccessful();

    $order->refresh();
    expect($order->status)->toBe(BarOrder::STATUS_VOID)
        ->and($order->stock_returned_at)->not->toBeNull();

    $onHand = StockOnHand::query()->firstOrFail();
    expect((float) $onHand->quantity_on_hand)->toBe(5.0);
});
