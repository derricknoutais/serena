<?php

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\Frontdesk\ReservationsIndexData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

it('returns consistent date strings for planner events', function (): void {
    $user = User::factory()->create();
    $tenantId = $user->tenant_id;

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenantId,
        'name' => 'Hotel Test',
        'code' => 'HTL',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'address' => '1 Test St',
        'city' => 'Douala',
        'country' => 'CM',
        'check_in_time' => '14:00',
        'check_out_time' => '11:00',
    ]);

    $user->forceFill(['active_hotel_id' => $hotel->id])->save();

    $roomType = RoomType::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotel->id,
        'name' => 'Deluxe',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 1000,
        'description' => null,
    ]);

    $room = Room::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => '101',
        'floor' => '1',
        'status' => 'available',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $guest = Guest::query()->create([
        'tenant_id' => $tenantId,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'guest@example.com',
        'phone' => null,
        'document_type' => null,
        'document_number' => null,
        'address' => null,
        'city' => null,
        'country' => null,
        'notes' => null,
    ]);

    $checkIn = Carbon::create(2024, 5, 1, 15, 0, 0, 'UTC');
    $actualCheckIn = Carbon::create(2024, 5, 1, 16, 30, 0, 'UTC');
    $checkOut = Carbon::create(2024, 5, 5, 11, 0, 0, 'UTC');

    Reservation::query()->create([
        'tenant_id' => $tenantId,
        'hotel_id' => $hotel->id,
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'room_id' => $room->id,
        'offer_id' => null,
        'code' => 'RSV-100',
        'status' => Reservation::STATUS_CONFIRMED,
        'source' => 'web',
        'offer_name' => null,
        'offer_kind' => null,
        'adults' => 2,
        'children' => 0,
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'expected_arrival_time' => null,
        'actual_check_in_at' => $actualCheckIn,
        'actual_check_out_at' => null,
        'currency' => 'XAF',
        'unit_price' => 150000,
        'base_amount' => 600000,
        'tax_amount' => 0,
        'total_amount' => 600000,
        'notes' => null,
        'booked_by_user_id' => $user->id,
    ]);

    $request = Request::create('/frontdesk/reservations', 'GET');
    $request->setUserResolver(static fn () => $user);

    $data = ReservationsIndexData::build($request);
    $event = $data['events'][0];

    expect($event['check_in_date'])->toBe('2024-05-01T15:00:00');
    expect($event['check_out_date'])->toBe('2024-05-05T11:00:00');
    expect($event['actual_check_in_at'])->toBe('2024-05-01T16:30:00');
});
