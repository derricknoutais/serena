<?php

require_once __DIR__.'/../FolioTestHelpers.php';

use App\Models\HousekeepingChecklist;
use App\Models\HousekeepingChecklistItem;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;

function setupHousekeepingChecklistTenant(): array
{
    $data = setupReservationEnvironment('hk-checklists');

    $data['user']->assignRole('owner');

    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    return $data;
}

it('creates an active checklist and deactivates others in the same scope', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'roomType' => $roomType,
        'user' => $user,
    ] = setupHousekeepingChecklistTenant();

    $existing = HousekeepingChecklist::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Checklist existante',
        'scope' => HousekeepingChecklist::SCOPE_ROOM_TYPE,
        'room_type_id' => $roomType->id,
        'is_active' => true,
    ]);

    $payload = [
        'name' => 'Checklist week-end',
        'scope' => HousekeepingChecklist::SCOPE_ROOM_TYPE,
        'room_type_id' => $roomType->id,
        'is_active' => true,
    ];

    $response = actingAs($user)->post(sprintf(
        'http://%s/ressources/housekeeping-checklists',
        tenantDomain($tenant),
    ), $payload);

    $response->assertRedirect();

    $newChecklist = HousekeepingChecklist::query()
        ->where('tenant_id', $tenant->id)
        ->where('hotel_id', $hotel->id)
        ->where('name', 'Checklist week-end')
        ->firstOrFail();

    expect($newChecklist->is_active)->toBeTrue();
    expect($existing->fresh()->is_active)->toBeFalse();
});

it('creates updates and reorders checklist items', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
    ] = setupHousekeepingChecklistTenant();

    $checklist = HousekeepingChecklist::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Checklist items',
        'scope' => HousekeepingChecklist::SCOPE_GLOBAL,
        'room_type_id' => null,
        'is_active' => false,
    ]);

    actingAs($user)->post(sprintf(
        'http://%s/ressources/housekeeping-checklists/%s/items',
        tenantDomain($tenant),
        $checklist->id,
    ), [
        'label' => 'Miroir',
        'is_required' => true,
        'is_active' => true,
    ])->assertRedirect();

    actingAs($user)->post(sprintf(
        'http://%s/ressources/housekeeping-checklists/%s/items',
        tenantDomain($tenant),
        $checklist->id,
    ), [
        'label' => 'Sol',
        'is_required' => false,
        'is_active' => true,
    ])->assertRedirect();

    $firstItem = HousekeepingChecklistItem::query()
        ->where('checklist_id', $checklist->id)
        ->where('label', 'Miroir')
        ->firstOrFail();

    $secondItem = HousekeepingChecklistItem::query()
        ->where('checklist_id', $checklist->id)
        ->where('label', 'Sol')
        ->firstOrFail();

    actingAs($user)->put(sprintf(
        'http://%s/ressources/housekeeping-checklists/%s/items/%s',
        tenantDomain($tenant),
        $checklist->id,
        $firstItem->id,
    ), [
        'label' => 'Miroir propre',
        'is_required' => false,
        'is_active' => false,
    ])->assertRedirect();

    $updatedItem = $firstItem->fresh();

    expect($updatedItem->label)->toBe('Miroir propre')
        ->and($updatedItem->is_required)->toBeFalse()
        ->and($updatedItem->is_active)->toBeFalse();

    actingAs($user)->post(sprintf(
        'http://%s/ressources/housekeeping-checklists/%s/items/reorder',
        tenantDomain($tenant),
        $checklist->id,
    ), [
        'items' => [
            ['id' => $secondItem->id, 'sort_order' => 0],
            ['id' => $updatedItem->id, 'sort_order' => 1],
        ],
    ])->assertRedirect();

    expect($secondItem->fresh()->sort_order)->toBe(0)
        ->and($updatedItem->fresh()->sort_order)->toBe(1);
});
