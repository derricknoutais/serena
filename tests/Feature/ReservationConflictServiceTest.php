<?php

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\ReservationConflictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function makeRoom(int $hotelId, string $tenantId, string $number, int $roomTypeId): Room
{
    return Room::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotelId,
        'number' => $number,
        'room_type_id' => $roomTypeId,
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);
}

it('detects overlapping room conflict', function () {
    $service = app(ReservationConflictService::class);
    $tenantId = 'tenant-1';
    $hotelId = 1;
    $roomType = RoomType::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotelId,
        'name' => 'Deluxe',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 1000,
    ]);
    $room = makeRoom($hotelId, $tenantId, '101', $roomType->id);

    $reservation = Reservation::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotelId,
        'room_id' => $room->id,
        'room_type_id' => $roomType->id,
        'guest_id' => 1,
        'code' => 'RSV-1',
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-01-10',
        'check_out_date' => '2025-01-12',
        'currency' => 'XAF',
        'unit_price' => 100,
        'base_amount' => 100,
        'tax_amount' => 0,
        'total_amount' => 100,
    ]);

    $conflict = $service->detectRoomConflict(
        $hotelId,
        $room->id,
        Carbon::parse('2025-01-11'),
        Carbon::parse('2025-01-13'),
    );

    expect($conflict)->not->toBeNull()
        ->and($conflict['id'])->toBe($reservation->id);
});

it('ignores non-overlapping end date', function () {
    $service = app(ReservationConflictService::class);
    $tenantId = 'tenant-1';
    $hotelId = 1;
    $roomType = RoomType::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotelId,
        'name' => 'Standard',
        'capacity_adults' => 2,
        'capacity_children' => 0,
        'base_price' => 800,
    ]);
    $room = makeRoom($hotelId, $tenantId, '102', $roomType->id);

    Reservation::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotelId,
        'room_id' => $room->id,
        'room_type_id' => $roomType->id,
        'guest_id' => 1,
        'code' => 'RSV-2',
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-02-01',
        'check_out_date' => '2025-02-03',
        'currency' => 'XAF',
        'unit_price' => 100,
        'base_amount' => 100,
        'tax_amount' => 0,
        'total_amount' => 100,
    ]);

    $conflict = $service->detectRoomConflict(
        $hotelId,
        $room->id,
        Carbon::parse('2025-02-03'),
        Carbon::parse('2025-02-05'),
    );

    expect($conflict)->toBeNull();
});

it('ignores cancelled reservations for conflict', function () {
    $service = app(ReservationConflictService::class);
    $tenantId = 'tenant-1';
    $hotelId = 1;
    $roomType = RoomType::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotelId,
        'name' => 'Suite',
        'capacity_adults' => 2,
        'capacity_children' => 2,
        'base_price' => 1500,
    ]);
    $room = makeRoom($hotelId, $tenantId, '103', $roomType->id);

    Reservation::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotelId,
        'room_id' => $room->id,
        'room_type_id' => $roomType->id,
        'guest_id' => 1,
        'code' => 'RSV-3',
        'status' => Reservation::STATUS_CANCELLED,
        'check_in_date' => '2025-03-01',
        'check_out_date' => '2025-03-04',
        'currency' => 'XAF',
        'unit_price' => 100,
        'base_amount' => 100,
        'tax_amount' => 0,
        'total_amount' => 100,
    ]);

    $conflict = $service->detectRoomConflict(
        $hotelId,
        $room->id,
        Carbon::parse('2025-03-02'),
        Carbon::parse('2025-03-05'),
    );

    expect($conflict)->toBeNull();
});
