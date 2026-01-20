<?php

use App\Models\Hotel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockItem;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('creates a product without account code input', function (): void {
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Products Hotel',
        'slug' => 'products-hotel',
        'plan' => 'standard',
        'contact_email' => 'products@hotel.test',
        'data' => [
            'name' => 'Products Hotel',
            'slug' => 'products-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'products-hotel.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Products Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $category = ProductCategory::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Boissons',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('products.create');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $this->actingAs($user)
        ->post('http://products-hotel.serena.test/settings/resources/products', [
            'product_category_id' => $category->getKey(),
            'name' => 'Cafe',
            'sku' => 'CF-001',
            'unit_price' => 2500,
            'tax_id' => null,
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('products', [
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Cafe',
        'account_code' => '',
    ]);
});

it('updates a product without account code input', function (): void {
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Products Hotel 2',
        'slug' => 'products-hotel-2',
        'plan' => 'standard',
        'contact_email' => 'products2@hotel.test',
        'data' => [
            'name' => 'Products Hotel 2',
            'slug' => 'products-hotel-2',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'products-hotel-2.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Products Hotel 2',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $category = ProductCategory::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Snacks',
    ]);

    $product = Product::query()->forceCreate([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'product_category_id' => $category->getKey(),
        'name' => 'Chips',
        'sku' => 'CH-01',
        'unit_price' => 1500,
        'tax_id' => null,
        'account_code' => 'AC-100',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('products.update');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $this->actingAs($user)
        ->put("http://products-hotel-2.serena.test/settings/resources/products/{$product->id}", [
            'product_category_id' => $category->getKey(),
            'name' => 'Chips Salt',
            'sku' => 'CH-01',
            'unit_price' => 1600,
            'tax_id' => null,
            'is_active' => true,
        ])
        ->assertRedirect();

    $product->refresh();

    expect($product->name)->toBe('Chips Salt')
        ->and((float) $product->unit_price)->toBe(1600.0)
        ->and($product->account_code)->toBe('AC-100');
});

it('creates a stock item when manage stock is enabled', function (): void {
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Products Hotel 3',
        'slug' => 'products-hotel-3',
        'plan' => 'standard',
        'contact_email' => 'products3@hotel.test',
        'data' => [
            'name' => 'Products Hotel 3',
            'slug' => 'products-hotel-3',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'products-hotel-3.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Products Hotel 3',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $category = ProductCategory::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Boissons',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('products.create');
    $user->givePermissionTo('stock.manage_bar_settings');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $this->actingAs($user)
        ->post('http://products-hotel-3.serena.test/settings/resources/products', [
            'product_category_id' => $category->getKey(),
            'name' => 'Jus mangue',
            'sku' => 'JM-001',
            'unit_price' => 2000,
            'tax_id' => null,
            'is_active' => true,
            'manage_stock' => true,
            'stock_quantity_per_unit' => 1,
            'default_purchase_price' => 850,
        ])
        ->assertRedirect();

    $product = Product::query()->where('hotel_id', $hotel->getKey())->firstOrFail();

    expect($product->manage_stock)->toBeTrue()
        ->and($product->stock_item_id)->not->toBeNull();

    $this->assertDatabaseHas('stock_items', [
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'id' => $product->stock_item_id,
        'item_category' => 'bar',
        'name' => 'Jus mangue',
    ]);

    $stockItem = StockItem::query()->find($product->stock_item_id);
    expect((float) $stockItem->default_purchase_price)->toBe(850.0);
});

it('creates a stock item when enabling manage stock on update', function (): void {
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Products Hotel 4',
        'slug' => 'products-hotel-4',
        'plan' => 'standard',
        'contact_email' => 'products4@hotel.test',
        'data' => [
            'name' => 'Products Hotel 4',
            'slug' => 'products-hotel-4',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'products-hotel-4.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Products Hotel 4',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $category = ProductCategory::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Snacks',
    ]);

    $product = Product::query()->forceCreate([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'product_category_id' => $category->getKey(),
        'name' => 'Biscuit',
        'sku' => 'BS-001',
        'unit_price' => 800,
        'tax_id' => null,
        'account_code' => '',
        'is_active' => true,
        'manage_stock' => false,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('products.update');
    $user->givePermissionTo('stock.manage_bar_settings');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $this->actingAs($user)
        ->put("http://products-hotel-4.serena.test/settings/resources/products/{$product->id}", [
            'product_category_id' => $category->getKey(),
            'name' => 'Biscuit',
            'sku' => 'BS-001',
            'unit_price' => 800,
            'tax_id' => null,
            'is_active' => true,
            'manage_stock' => true,
            'stock_quantity_per_unit' => 1,
            'default_purchase_price' => 450,
        ])
        ->assertRedirect();

    $product->refresh();

    expect($product->manage_stock)->toBeTrue()
        ->and($product->stock_item_id)->not->toBeNull();

    $stockItem = StockItem::query()->find($product->stock_item_id);
    expect($stockItem)->not->toBeNull()
        ->and((float) $stockItem->default_purchase_price)->toBe(450.0);
});
