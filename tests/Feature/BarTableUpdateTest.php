<?php

use App\Models\BarTable;
use App\Models\Hotel;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

it('updates a bar table with put', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Bar Hotel',
        'slug' => 'bar-hotel',
        'plan' => 'standard',
        'contact_email' => 'bar@hotel.test',
        'data' => [
            'name' => 'Bar Hotel',
            'slug' => 'bar-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => 'bar-hotel.serena.test']);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Bar Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $table = BarTable::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Table 1',
        'area' => 'Terrasse',
        'capacity' => 4,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('pos.tables.manage');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    $this->actingAs($user)
        ->put("http://bar-hotel.serena.test/settings/resources/bar-tables/{$table->id}", [
            'name' => 'Table 1B',
            'area' => 'Lounge',
            'capacity' => 6,
            'is_active' => false,
            'sort_order' => 2,
        ])
        ->assertRedirect();

    $table->refresh();

    expect($table->name)->toBe('Table 1B')
        ->and($table->area)->toBe('Lounge')
        ->and($table->capacity)->toBe(6)
        ->and($table->is_active)->toBeFalse()
        ->and($table->sort_order)->toBe(2);
});
