<?php

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

it('resets reservations and rooms for a scoped hotel', function (): void {
    Carbon::setTestNow('2026-01-22 09:00:00');

    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Reset Tenant',
        'slug' => 'reset-tenant',
        'plan' => 'standard',
    ]);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Reset Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00:00',
        'check_out_time' => '12:00:00',
    ]);

    $otherHotel = Hotel::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Other Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00:00',
        'check_out_time' => '12:00:00',
    ]);

    $roomType = RoomType::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'name' => 'Standard',
        'capacity_adults' => 2,
        'capacity_children' => 0,
        'base_price' => 10000,
    ]);

    $otherRoomType = RoomType::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $otherHotel->id,
        'name' => 'Standard',
        'capacity_adults' => 2,
        'capacity_children' => 0,
        'base_price' => 10000,
    ]);

    $room = Room::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '101',
        'status' => Room::STATUS_IN_USE,
        'hk_status' => Room::HK_STATUS_DIRTY,
    ]);

    $roomOutOfOrder = Room::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '102',
        'status' => Room::STATUS_OUT_OF_ORDER,
        'hk_status' => Room::HK_STATUS_DIRTY,
    ]);

    $otherRoom = Room::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $otherHotel->id,
        'room_type_id' => $otherRoomType->id,
        'number' => '201',
        'status' => Room::STATUS_IN_USE,
        'hk_status' => Room::HK_STATUS_DIRTY,
    ]);

    $guest = Guest::query()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Jean',
        'last_name' => 'Doe',
        'email' => 'guest@hotel.test',
        'phone' => '+237600000001',
    ]);

    $reservation = Reservation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'room_id' => $room->id,
        'code' => 'RES-RESET-1',
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2026-01-20 12:00:00',
        'check_out_date' => '2026-01-25 11:00:00',
        'actual_check_in_at' => '2026-01-20 12:00:00',
        'currency' => 'XAF',
        'unit_price' => 10000,
        'base_amount' => 50000,
        'tax_amount' => 0,
        'total_amount' => 50000,
    ]);

    $reservationConfirmed = Reservation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'room_id' => $roomOutOfOrder->id,
        'code' => 'RES-RESET-2',
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2026-01-21 12:00:00',
        'check_out_date' => '2026-01-23 11:00:00',
        'actual_check_in_at' => null,
        'currency' => 'XAF',
        'unit_price' => 10000,
        'base_amount' => 20000,
        'tax_amount' => 0,
        'total_amount' => 20000,
    ]);

    $otherReservation = Reservation::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $otherHotel->id,
        'guest_id' => $guest->id,
        'room_type_id' => $otherRoomType->id,
        'room_id' => $otherRoom->id,
        'code' => 'RES-RESET-3',
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => '2026-01-20 12:00:00',
        'check_out_date' => '2026-01-25 11:00:00',
        'actual_check_in_at' => '2026-01-20 12:00:00',
        'currency' => 'XAF',
        'unit_price' => 10000,
        'base_amount' => 50000,
        'tax_amount' => 0,
        'total_amount' => 50000,
    ]);

    Artisan::call('dev:reset-roomboard', [
        '--tenant' => $tenant->id,
        '--hotel' => $hotel->id,
    ]);

    $reservation->refresh();
    $reservationConfirmed->refresh();
    $otherReservation->refresh();
    $room->refresh();
    $roomOutOfOrder->refresh();
    $otherRoom->refresh();

    expect($reservation->status)->toBe(Reservation::STATUS_CHECKED_OUT)
        ->and($reservation->actual_check_out_at)->not->toBeNull()
        ->and($reservationConfirmed->status)->toBe(Reservation::STATUS_CHECKED_OUT)
        ->and($reservationConfirmed->actual_check_out_at)->not->toBeNull()
        ->and($otherReservation->status)->toBe(Reservation::STATUS_IN_HOUSE)
        ->and($room->hk_status)->toBe(Room::HK_STATUS_INSPECTED)
        ->and($room->status)->toBe(Room::STATUS_AVAILABLE)
        ->and($roomOutOfOrder->hk_status)->toBe(Room::HK_STATUS_INSPECTED)
        ->and($roomOutOfOrder->status)->toBe(Room::STATUS_OUT_OF_ORDER)
        ->and($otherRoom->hk_status)->toBe(Room::HK_STATUS_DIRTY);

    Carbon::setTestNow();
});
