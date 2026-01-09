<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\HousekeepingChecklist;
use App\Models\HousekeepingChecklistItem;
use App\Models\HousekeepingTask;
use App\Models\Room;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;

function setupRedoEnvironment(): array
{
    $data = setupReservationEnvironment('hk-redo-flow');

    $data['user']->assignRole('owner');

    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);

    return $data;
}

it('creates a redo inspection task after redo cleaning is finished', function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'room' => $room,
        'user' => $user,
    ] = setupRedoEnvironment();

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
        'label' => 'Ventilation',
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

    $room->update(['hk_status' => Room::HK_STATUS_AWAITING_INSPECTION]);

    $domain = tenantDomain($tenant);

    actingAs($user)->post(sprintf(
        'http://%s/hk/rooms/%s/inspections/finish',
        $domain,
        $room->id,
    ), [
        'items' => [
            ['checklist_item_id' => $item->id, 'is_ok' => false, 'note' => 'A refaire'],
        ],
    ])->assertOk();

    $inspection->refresh();

    expect($inspection->status)->toBe(HousekeepingTask::STATUS_DONE)
        ->and($inspection->outcome)->toBe(HousekeepingTask::OUTCOME_FAILED)
        ->and($room->fresh()->hk_status)->toBe(Room::HK_STATUS_REDO);

    $redoCleaning = HousekeepingTask::query()
        ->where('room_id', $room->id)
        ->where('type', HousekeepingTask::TYPE_REDO_CLEANING)
        ->where('status', HousekeepingTask::STATUS_PENDING)
        ->firstOrFail();

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

    $redoInspection = HousekeepingTask::query()
        ->where('room_id', $room->id)
        ->where('type', HousekeepingTask::TYPE_REDO_INSPECTION)
        ->where('status', HousekeepingTask::STATUS_PENDING)
        ->firstOrFail();

    expect($redoCleaning->fresh()->status)->toBe(HousekeepingTask::STATUS_DONE)
        ->and($redoInspection)->not->toBeNull()
        ->and($room->fresh()->hk_status)->toBe(Room::HK_STATUS_AWAITING_INSPECTION);
});
