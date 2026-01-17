<?php

use App\Models\Hotel;
use App\Models\StockPurchase;
use App\Models\StorageLocation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('receives a draft purchase', function (): void {
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
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $location = StorageLocation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Magasin',
        'code' => 'MAIN',
        'category' => 'stock',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('stock.purchases.receive');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $purchase = StockPurchase::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'storage_location_id' => $location->getKey(),
        'status' => StockPurchase::STATUS_DRAFT,
        'currency' => 'XAF',
        'subtotal_amount' => 0,
        'total_amount' => 0,
        'created_by_user_id' => $user->id,
    ]);

    mock(InventoryService::class)
        ->shouldReceive('receivePurchase')
        ->once()
        ->withArgs(fn ($passedPurchase, $actor) => $passedPurchase->is($purchase) && $actor->is($user));

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->post("http://stock-hotel.serena.test/stock/purchases/{$purchase->id}/receive")
        ->assertOk();
});

it('voids a draft purchase', function (): void {
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Stock Hotel 2',
        'slug' => 'stock-hotel-2',
        'plan' => 'standard',
        'contact_email' => 'stock2@hotel.test',
        'data' => [
            'name' => 'Stock Hotel 2',
            'slug' => 'stock-hotel-2',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'stock-hotel-2.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Stock Hotel 2',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $location = StorageLocation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Entrepot',
        'code' => 'ENT',
        'category' => 'stock',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('stock.purchases.update');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $purchase = StockPurchase::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'storage_location_id' => $location->getKey(),
        'status' => StockPurchase::STATUS_DRAFT,
        'currency' => 'XAF',
        'subtotal_amount' => 0,
        'total_amount' => 0,
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->post("http://stock-hotel-2.serena.test/stock/purchases/{$purchase->id}/void")
        ->assertOk();

    $purchase->refresh();

    expect($purchase->status)->toBe(StockPurchase::STATUS_VOID);
});
