<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\HousekeepingChecklist;
use App\Models\HousekeepingChecklistItem;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Notifications\GenericPushNotification;
use App\Services\HousekeepingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('sends a push notification when a room becomes dirty', function (): void {
    Notification::fake();

    [
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('hk-push-dirty');

    $user->assignRole('owner');

    $service = app(HousekeepingService::class);
    $service->markRoomDirtyAfterCheckout($room, $user);

    expect($room->refresh()->hk_status)->toBe(Room::HK_STATUS_DIRTY);

    Notification::assertSentTo($user, GenericPushNotification::class, function (GenericPushNotification $notification) use ($room, $user): bool {
        return $notification->title === 'Chambre sale'
            && str_contains($notification->body, $room->number)
            && str_contains($notification->body, Room::HK_STATUS_DIRTY)
            && str_contains($notification->body, $user->name);
    });
});

it('sends a push notification when inspection is approved or refused', function (bool $approved, string $expectedStatus, string $expectedTitle): void {
    Notification::fake();

    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('hk-push-inspection');

    $user->assignRole('owner');

    $room->update([
        'hk_status' => Room::HK_STATUS_AWAITING_INSPECTION,
    ]);

    $checklist = HousekeepingChecklist::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Checklist',
        'scope' => HousekeepingChecklist::SCOPE_GLOBAL,
        'is_active' => true,
    ]);

    $item = HousekeepingChecklistItem::query()->create([
        'checklist_id' => $checklist->id,
        'label' => 'Test item',
        'sort_order' => 1,
        'is_required' => false,
        'is_active' => true,
    ]);

    $task = HousekeepingTask::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'type' => HousekeepingTask::TYPE_INSPECTION,
        'status' => HousekeepingTask::STATUS_IN_PROGRESS,
        'priority' => HousekeepingTask::PRIORITY_NORMAL,
        'created_from' => HousekeepingTask::SOURCE_RECEPTION,
    ]);

    $payload = $approved
        ? [['checklist_item_id' => $item->id, 'is_ok' => true, 'note' => null]]
        : [['checklist_item_id' => $item->id, 'is_ok' => false, 'note' => 'Problème']];

    $service = app(HousekeepingService::class);
    $service->finishInspection($task, $user, $payload);

    expect($room->refresh()->hk_status)->toBe($expectedStatus);

    Notification::assertSentTo($user, GenericPushNotification::class, function (GenericPushNotification $notification) use ($room, $user, $expectedStatus, $expectedTitle): bool {
        return $notification->title === $expectedTitle
            && str_contains($notification->body, $room->number)
            && str_contains($notification->body, $expectedStatus)
            && str_contains($notification->body, $user->name);
    });
})->with([
    'approved' => [true, Room::HK_STATUS_INSPECTED, 'Inspection approuvée'],
    'refused' => [false, Room::HK_STATUS_REDO, 'Inspection refusée'],
]);
