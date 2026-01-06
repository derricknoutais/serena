<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\HousekeepingChecklist;
use App\Models\HousekeepingChecklistItem;
use App\Models\HousekeepingTask;
use App\Models\HousekeepingTaskChecklistItem;
use App\Models\Room;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;

function setupInspectionEnvironment(): array
{
    $data = setupReservationEnvironment('hk-inspection');

    $data['user']->assignRole('owner');

    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    return $data;
}

it('creates an inspection task after cleaning and completes inspection', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'room' => $room,
        'user' => $user,
    ] = setupInspectionEnvironment();

    $checklist = HousekeepingChecklist::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Checklist globale',
        'scope' => HousekeepingChecklist::SCOPE_GLOBAL,
        'room_type_id' => null,
        'is_active' => true,
    ]);

    $item = HousekeepingChecklistItem::query()->create([
        'checklist_id' => $checklist->id,
        'label' => 'Salle de bain',
        'sort_order' => 0,
        'is_required' => true,
        'is_active' => true,
    ]);

    $task = HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'type' => HousekeepingTask::TYPE_CLEANING,
        'status' => HousekeepingTask::STATUS_PENDING,
        'priority' => HousekeepingTask::PRIORITY_NORMAL,
        'created_from' => HousekeepingTask::SOURCE_CHECKOUT,
    ]);

    $domain = tenantDomain($tenant);

    actingAs($user)->post(sprintf(
        'http://%s/hk/rooms/%s/tasks/start',
        $domain,
        $room->id,
    ))->assertOk();

    actingAs($user)->post(sprintf(
        'http://%s/hk/rooms/%s/tasks/finish',
        $domain,
        $room->id,
    ))->assertOk();

    $inspection = HousekeepingTask::query()
        ->where('room_id', $room->id)
        ->where('type', HousekeepingTask::TYPE_INSPECTION)
        ->firstOrFail();

    expect($task->fresh()->status)->toBe(HousekeepingTask::STATUS_DONE)
        ->and($room->fresh()->hk_status)->toBe(Room::HK_STATUS_AWAITING_INSPECTION)
        ->and($inspection->status)->toBe(HousekeepingTask::STATUS_PENDING);

    actingAs($user)->post(sprintf(
        'http://%s/hk/rooms/%s/inspections/start',
        $domain,
        $room->id,
    ))->assertOk();

    actingAs($user)->post(sprintf(
        'http://%s/hk/rooms/%s/inspections/finish',
        $domain,
        $room->id,
    ), [
        'items' => [
            ['checklist_item_id' => $item->id, 'is_ok' => true, 'note' => null],
        ],
    ])->assertOk();

    $inspection->refresh();

    expect($inspection->status)->toBe(HousekeepingTask::STATUS_DONE)
        ->and($inspection->outcome)->toBe(HousekeepingTask::OUTCOME_PASSED)
        ->and($room->fresh()->hk_status)->toBe(Room::HK_STATUS_INSPECTED);

    $responseItem = HousekeepingTaskChecklistItem::query()
        ->where('task_id', $inspection->id)
        ->where('checklist_item_id', $item->id)
        ->firstOrFail();

    expect($responseItem->is_ok)->toBeTrue();
});

it('creates a new cleaning task when inspection fails', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'room' => $room,
        'user' => $user,
    ] = setupInspectionEnvironment();

    $checklist = HousekeepingChecklist::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Checklist globale',
        'scope' => HousekeepingChecklist::SCOPE_GLOBAL,
        'room_type_id' => null,
        'is_active' => true,
    ]);

    $item = HousekeepingChecklistItem::query()->create([
        'checklist_id' => $checklist->id,
        'label' => 'Fenêtre',
        'sort_order' => 0,
        'is_required' => true,
        'is_active' => true,
    ]);

    $inspection = HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'type' => HousekeepingTask::TYPE_INSPECTION,
        'status' => HousekeepingTask::STATUS_IN_PROGRESS,
        'priority' => HousekeepingTask::PRIORITY_NORMAL,
        'created_from' => HousekeepingTask::SOURCE_CHECKOUT,
        'started_at' => now(),
    ]);

    $domain = tenantDomain($tenant);

    actingAs($user)->post(sprintf(
        'http://%s/hk/rooms/%s/inspections/finish',
        $domain,
        $room->id,
    ), [
        'items' => [
            ['checklist_item_id' => $item->id, 'is_ok' => false, 'note' => 'Vitres sales'],
        ],
    ])->assertOk();

    $inspection->refresh();

    expect($inspection->status)->toBe(HousekeepingTask::STATUS_DONE)
        ->and($inspection->outcome)->toBe(HousekeepingTask::OUTCOME_FAILED)
        ->and($room->fresh()->hk_status)->toBe(Room::HK_STATUS_REDO);

    $newCleaningTask = HousekeepingTask::query()
        ->where('room_id', $room->id)
        ->where('type', HousekeepingTask::TYPE_CLEANING)
        ->where('status', HousekeepingTask::STATUS_PENDING)
        ->first();

    expect($newCleaningTask)->not->toBeNull();
});
