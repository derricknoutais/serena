<?php

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\GenericPushNotification;
use App\Services\FolioBillingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * @return array{
 *   tenant: Tenant,
 *   hotel: Hotel,
 *   user: User,
 *   roomType: RoomType,
 *   oldRoom: Room,
 *   newRoom: Room,
 *   reservation: Reservation,
 *   domain: string
 * }
 */
function makeRoomMoveSetup(string $domain, array $overrides = []): array
{
    $tenant = Tenant::query()->create([
        'id' => (string) Str::uuid(),
        'name' => 'Move Hotel',
        'slug' => 'move-hotel',
        'plan' => 'standard',
        'contact_email' => 'move@hotel.test',
        'data' => [
            'name' => 'Move Hotel',
            'slug' => 'move-hotel',
        ],
    ]);
    $tenant->domains()->create(['domain' => $domain]);

    $hotel = Hotel::query()->create([
        'tenant_id' => $tenant->getKey(),
        'name' => 'Move Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $roomType = RoomType::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'name' => 'Standard',
        'capacity_adults' => 2,
        'capacity_children' => 0,
        'base_price' => 15000,
    ]);

    $oldRoom = Room::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'room_type_id' => $roomType->getKey(),
        'number' => '101',
        'status' => Room::STATUS_IN_USE,
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $newRoom = Room::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'room_type_id' => $roomType->getKey(),
        'number' => '102',
        'status' => Room::STATUS_AVAILABLE,
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $guest = Guest::query()->create([
        'tenant_id' => $tenant->getKey(),
        'first_name' => 'Jean',
        'last_name' => 'Doe',
        'email' => 'guest@hotel.test',
        'phone' => '+237600000001',
    ]);

    $checkIn = Carbon::now()->startOfDay();
    $checkOut = (clone $checkIn)->addDays(2);

    $reservation = Reservation::query()->create([
        'tenant_id' => $tenant->getKey(),
        'hotel_id' => $hotel->getKey(),
        'guest_id' => $guest->getKey(),
        'room_type_id' => $roomType->getKey(),
        'room_id' => $oldRoom->getKey(),
        'code' => 'RES-ROOM-MOVE',
        'status' => Reservation::STATUS_IN_HOUSE,
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'actual_check_in_at' => Carbon::now(),
        'currency' => 'XAF',
        'unit_price' => 15000,
        'base_amount' => 30000,
        'tax_amount' => 0,
        'total_amount' => 30000,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'active_hotel_id' => $hotel->getKey(),
    ]);
    $user->assignRole('owner');
    $user->givePermissionTo('reservations.change_room');
    $user->hotels()->syncWithoutDetaching([$hotel->getKey()]);

    return array_merge([
        'tenant' => $tenant,
        'hotel' => $hotel,
        'user' => $user,
        'roomType' => $roomType,
        'oldRoom' => $oldRoom,
        'newRoom' => $newRoom,
        'reservation' => $reservation,
        'domain' => $domain,
    ], $overrides);
}

beforeEach(function (): void {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('moves room and keeps hk status when not used', function (): void {
    $setup = makeRoomMoveSetup('move-not-used.serena.test');
    Notification::fake();
    $offer = Offer::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'name' => 'Tarif souple',
        'kind' => 'night',
        'billing_mode' => 'per_stay',
        'is_active' => true,
    ]);

    $newRoomType = RoomType::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'name' => 'Suite',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 25000,
    ]);

    $setup['newRoom']->update([
        'room_type_id' => $newRoomType->getKey(),
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'room_type_id' => $setup['roomType']->getKey(),
        'offer_id' => $offer->getKey(),
        'price' => 15000,
        'currency' => 'XAF',
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'room_type_id' => $newRoomType->getKey(),
        'offer_id' => $offer->getKey(),
        'price' => 25000,
        'currency' => 'XAF',
    ]);

    $setup['reservation']->update([
        'offer_id' => $offer->getKey(),
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
        'check_in_date' => '2025-05-01 00:00:00',
        'check_out_date' => '2025-05-04 00:00:00',
        'actual_check_in_at' => '2025-05-01 00:00:00',
        'unit_price' => 15000,
        'base_amount' => 45000,
        'total_amount' => 45000,
    ]);

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'not_used',
                'moved_at' => '2025-05-01 00:00:00',
            ],
        )
        ->assertOk();

    $setup['oldRoom']->refresh();
    $setup['newRoom']->refresh();

    $folio = app(FolioBillingService::class)
        ->ensureMainFolioForReservation($setup['reservation']->fresh());
    $stayItems = $folio->items()->where('is_stay_item', true)->get();
    $adjustments = $folio->items()
        ->where('type', 'stay_adjustment')
        ->where('meta->kind', 'room_move_delta')
        ->count();

    expect($setup['oldRoom']->status)->toBe(Room::STATUS_AVAILABLE)
        ->and($setup['oldRoom']->hk_status)->toBe(Room::HK_STATUS_INSPECTED)
        ->and($setup['newRoom']->status)->toBe(Room::STATUS_IN_USE);

    expect($stayItems)->toHaveCount(1)
        ->and($stayItems->first()->unit_price)->toBe(25000.0)
        ->and($stayItems->first()->quantity)->toBe(3.0)
        ->and($stayItems->first()->meta['room_id'])->toBe($setup['newRoom']->getKey())
        ->and($stayItems->first()->meta['source'])->toBe('room_change')
        ->and($folio->balance)->toBe(105000.0)
        ->and($adjustments)->toBe(1);

    Notification::assertSentTo($setup['user'], GenericPushNotification::class, function (GenericPushNotification $notification) use ($setup): bool {
        return $notification->title === 'Changement de chambre'
            && str_contains($notification->body, $setup['oldRoom']->number)
            && str_contains($notification->body, $setup['newRoom']->number);
    });
});

it('moves room and marks old room dirty when used', function (): void {
    $setup = makeRoomMoveSetup('move-used.serena.test');
    $offer = Offer::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'name' => 'Tarif souple',
        'kind' => 'night',
        'billing_mode' => 'per_stay',
        'is_active' => true,
    ]);

    $newRoomType = RoomType::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'name' => 'Suite',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 25000,
    ]);

    $setup['newRoom']->update([
        'room_type_id' => $newRoomType->getKey(),
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'room_type_id' => $setup['roomType']->getKey(),
        'offer_id' => $offer->getKey(),
        'price' => 15000,
        'currency' => 'XAF',
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'room_type_id' => $newRoomType->getKey(),
        'offer_id' => $offer->getKey(),
        'price' => 25000,
        'currency' => 'XAF',
    ]);

    $setup['reservation']->update([
        'offer_id' => $offer->getKey(),
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
        'check_in_date' => '2025-05-01 00:00:00',
        'check_out_date' => '2025-05-04 00:00:00',
        'actual_check_in_at' => '2025-05-01 00:00:00',
        'unit_price' => 15000,
        'base_amount' => 45000,
        'total_amount' => 45000,
    ]);

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'used',
                'moved_at' => '2025-05-03 00:00:00',
            ],
        )
        ->assertOk();

    $setup['oldRoom']->refresh();
    $setup['newRoom']->refresh();

    $folio = app(FolioBillingService::class)
        ->ensureMainFolioForReservation($setup['reservation']->fresh());
    $stayItems = $folio->items()->where('is_stay_item', true)->orderBy('date')->get();
    $adjustments = $folio->items()
        ->where('type', 'stay_adjustment')
        ->where('meta->kind', 'room_move_delta')
        ->count();

    expect($setup['oldRoom']->status)->toBe(Room::STATUS_AVAILABLE)
        ->and($setup['oldRoom']->hk_status)->toBe(Room::HK_STATUS_DIRTY)
        ->and($setup['newRoom']->status)->toBe(Room::STATUS_IN_USE);

    expect($stayItems)->toHaveCount(1)
        ->and($stayItems[0]->quantity)->toBe(1.0)
        ->and($stayItems[0]->unit_price)->toBe(25000.0)
        ->and($stayItems[0]->meta['room_id'])->toBe($setup['newRoom']->getKey())
        ->and($folio->balance)->toBe(35000.0)
        ->and($adjustments)->toBe(1);
});

it('does not add a room move adjustment when prices match', function (): void {
    $setup = makeRoomMoveSetup('move-no-delta.serena.test');

    $setup['reservation']->update([
        'check_in_date' => '2025-05-01 00:00:00',
        'check_out_date' => '2025-05-04 00:00:00',
        'actual_check_in_at' => '2025-05-01 00:00:00',
        'unit_price' => 15000,
        'base_amount' => 45000,
        'total_amount' => 45000,
    ]);

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'used',
                'moved_at' => '2025-05-01 00:00:00',
            ],
        )
        ->assertOk();

    $folio = app(FolioBillingService::class)
        ->ensureMainFolioForReservation($setup['reservation']->fresh());
    $adjustments = $folio->items()
        ->where('type', 'stay_adjustment')
        ->where('meta->kind', 'room_move_delta')
        ->count();

    expect($adjustments)->toBe(0)
        ->and($folio->balance)->toBe(45000.0);
});

it('moves room and marks old room to inspect when usage is unknown', function (): void {
    $setup = makeRoomMoveSetup('move-unknown.serena.test');

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'unknown',
            ],
        )
        ->assertOk();

    $setup['oldRoom']->refresh();
    $setup['newRoom']->refresh();

    expect($setup['oldRoom']->status)->toBe(Room::STATUS_AVAILABLE)
        ->and($setup['oldRoom']->hk_status)->toBe(Room::HK_STATUS_AWAITING_INSPECTION)
        ->and($setup['newRoom']->status)->toBe(Room::STATUS_IN_USE);
});

it('cleans up old stay segments after multiple room changes', function (): void {
    $setup = makeRoomMoveSetup('move-multi.serena.test');
    $offer = Offer::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'name' => 'Tarif souple',
        'kind' => 'night',
        'billing_mode' => 'per_stay',
        'is_active' => true,
    ]);

    $secondRoomType = RoomType::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'name' => 'Suite',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 25000,
    ]);

    $thirdRoomType = RoomType::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'name' => 'Penthouse',
        'capacity_adults' => 2,
        'capacity_children' => 1,
        'base_price' => 30000,
    ]);

    $thirdRoom = Room::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'room_type_id' => $thirdRoomType->getKey(),
        'number' => '103',
        'status' => Room::STATUS_AVAILABLE,
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $setup['newRoom']->update([
        'room_type_id' => $secondRoomType->getKey(),
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'room_type_id' => $setup['roomType']->getKey(),
        'offer_id' => $offer->getKey(),
        'price' => 15000,
        'currency' => 'XAF',
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'room_type_id' => $secondRoomType->getKey(),
        'offer_id' => $offer->getKey(),
        'price' => 25000,
        'currency' => 'XAF',
    ]);

    OfferRoomTypePrice::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'room_type_id' => $thirdRoomType->getKey(),
        'offer_id' => $offer->getKey(),
        'price' => 30000,
        'currency' => 'XAF',
    ]);

    $setup['reservation']->update([
        'offer_id' => $offer->getKey(),
        'offer_name' => $offer->name,
        'offer_kind' => $offer->kind,
        'check_in_date' => '2025-05-01 00:00:00',
        'check_out_date' => '2025-05-04 00:00:00',
        'actual_check_in_at' => '2025-05-01 00:00:00',
        'unit_price' => 15000,
        'base_amount' => 45000,
        'total_amount' => 45000,
    ]);

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'used',
                'moved_at' => '2025-05-02 00:00:00',
            ],
        )
        ->assertOk();

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $thirdRoom->id,
                'vacated_usage' => 'used',
                'moved_at' => '2025-05-03 00:00:00',
            ],
        )
        ->assertOk();

    $folio = app(FolioBillingService::class)
        ->ensureMainFolioForReservation($setup['reservation']->fresh());
    $activeStayItems = $folio->items()->where('is_stay_item', true)->get();
    $allStayItems = $folio->items()->withTrashed()->where('is_stay_item', true)->get();
    $adjustments = $folio->items()
        ->where('type', 'stay_adjustment')
        ->where('meta->kind', 'room_move_delta')
        ->count();

    expect($activeStayItems)->toHaveCount(2)
        ->and($allStayItems)->toHaveCount(2)
        ->and($adjustments)->toBe(2);
});

it('ignores past checked out reservations when moving rooms', function (): void {
    Carbon::setTestNow('2026-01-21 10:00:00');

    $setup = makeRoomMoveSetup('move-past-checked-out.serena.test');

    $setup['reservation']->update([
        'check_in_date' => '2026-01-01 12:00:00',
        'check_out_date' => '2026-01-23 11:00:00',
        'actual_check_in_at' => '2026-01-01 12:00:00',
        'status' => Reservation::STATUS_IN_HOUSE,
    ]);

    Reservation::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'guest_id' => $setup['reservation']->guest_id,
        'room_type_id' => $setup['roomType']->getKey(),
        'room_id' => $setup['newRoom']->getKey(),
        'code' => 'RES-PAST',
        'status' => Reservation::STATUS_CHECKED_OUT,
        'source' => 'direct',
        'offer_name' => null,
        'offer_kind' => 'night',
        'adults' => 1,
        'children' => 0,
        'check_in_date' => '2026-01-03 12:00:00',
        'check_out_date' => '2026-01-04 11:00:00',
        'expected_arrival_time' => '12:00',
        'actual_check_in_at' => '2026-01-03 12:00:00',
        'actual_check_out_at' => '2026-01-04 11:00:00',
        'currency' => 'XAF',
        'unit_price' => 15000,
        'base_amount' => 15000,
        'tax_amount' => 0,
        'total_amount' => 15000,
    ]);

    $response = $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'used',
                'moved_at' => '2026-01-21 10:00:00',
            ],
        );

    $response->assertOk();

    Carbon::setTestNow();
});

it('blocks room move when a future reservation overlaps the remaining stay', function (): void {
    Carbon::setTestNow('2026-01-21 10:00:00');

    $setup = makeRoomMoveSetup('move-future-conflict.serena.test');

    $setup['reservation']->update([
        'check_in_date' => '2026-01-01 12:00:00',
        'check_out_date' => '2026-01-23 11:00:00',
        'actual_check_in_at' => '2026-01-01 12:00:00',
        'status' => Reservation::STATUS_IN_HOUSE,
    ]);

    Reservation::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'guest_id' => $setup['reservation']->guest_id,
        'room_type_id' => $setup['roomType']->getKey(),
        'room_id' => $setup['newRoom']->getKey(),
        'code' => 'RES-FUTURE',
        'status' => Reservation::STATUS_CONFIRMED,
        'source' => 'direct',
        'offer_name' => null,
        'offer_kind' => 'night',
        'adults' => 1,
        'children' => 0,
        'check_in_date' => '2026-01-22 14:00:00',
        'check_out_date' => '2026-01-24 11:00:00',
        'expected_arrival_time' => '14:00',
        'actual_check_in_at' => null,
        'actual_check_out_at' => null,
        'currency' => 'XAF',
        'unit_price' => 15000,
        'base_amount' => 30000,
        'tax_amount' => 0,
        'total_amount' => 30000,
    ]);

    $response = $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'used',
                'moved_at' => '2026-01-21 10:00:00',
            ],
        );

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('room_id');

    Carbon::setTestNow();
});

it('ignores active reservations outside the remaining window when moving rooms', function (): void {
    Carbon::setTestNow('2026-01-21 10:00:00');

    $setup = makeRoomMoveSetup('move-past-active.serena.test');

    $setup['reservation']->update([
        'check_in_date' => '2026-01-01 12:00:00',
        'check_out_date' => '2026-01-23 11:00:00',
        'actual_check_in_at' => '2026-01-01 12:00:00',
        'status' => Reservation::STATUS_IN_HOUSE,
    ]);

    Reservation::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $setup['hotel']->getKey(),
        'guest_id' => $setup['reservation']->guest_id,
        'room_type_id' => $setup['roomType']->getKey(),
        'room_id' => $setup['newRoom']->getKey(),
        'code' => 'RES-PAST-ACTIVE',
        'status' => Reservation::STATUS_CONFIRMED,
        'source' => 'direct',
        'offer_name' => null,
        'offer_kind' => 'night',
        'adults' => 1,
        'children' => 0,
        'check_in_date' => '2026-01-03 12:00:00',
        'check_out_date' => '2026-01-04 11:00:00',
        'expected_arrival_time' => '12:00',
        'actual_check_in_at' => null,
        'actual_check_out_at' => null,
        'currency' => 'XAF',
        'unit_price' => 15000,
        'base_amount' => 15000,
        'tax_amount' => 0,
        'total_amount' => 15000,
    ]);

    $response = $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $setup['newRoom']->id,
                'vacated_usage' => 'used',
                'moved_at' => '2026-01-21 10:00:00',
            ],
        );

    $response->assertOk();

    Carbon::setTestNow();
});

it('cannot move to a room from another hotel', function (): void {
    $setup = makeRoomMoveSetup('move-cross.serena.test');

    $otherHotel = Hotel::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'name' => 'Other Hotel',
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
    ]);

    $otherRoomType = RoomType::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $otherHotel->getKey(),
        'name' => 'Other Type',
        'capacity_adults' => 2,
        'capacity_children' => 0,
        'base_price' => 12000,
    ]);

    $otherRoom = Room::query()->create([
        'tenant_id' => $setup['tenant']->getKey(),
        'hotel_id' => $otherHotel->getKey(),
        'room_type_id' => $otherRoomType->getKey(),
        'number' => '201',
        'status' => Room::STATUS_AVAILABLE,
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    $this->actingAs($setup['user'])
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(
            "http://{$setup['domain']}/reservations/{$setup['reservation']->id}/stay/room",
            [
                'room_id' => $otherRoom->id,
                'vacated_usage' => 'not_used',
            ],
        )
        ->assertNotFound();
});
