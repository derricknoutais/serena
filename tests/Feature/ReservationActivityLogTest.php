<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\Activity;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationStateMachine;
use App\Support\ActivityFormatter;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => [],
    ]);

    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
    ]);
});

it('logs activity when a reservation is created', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'roomType' => $roomType,
        'guest' => $guest,
        'user' => $user,
    ] = setupReservationEnvironment('reservation-create-log');

    $user->assignRole('owner');

    $room = Room::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $roomType->id,
        'number' => 'ACT-201',
        'floor' => '2',
        'status' => 'active',
        'hk_status' => Room::HK_STATUS_INSPECTED,
    ]);

    actingAs($user);

    $checkIn = Carbon::now()->addDays(3)->toDateString();
    $checkOut = Carbon::now()->addDays(4)->toDateString();

    post(sprintf('http://%s/reservations', tenantDomain($tenant)), [
        'code' => 'RES-NEW-ACT',
        'guest_id' => $guest->id,
        'room_type_id' => $roomType->id,
        'room_id' => $room->id,
        'offer_id' => null,
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'currency' => 'XAF',
        'unit_price' => 10000,
        'base_amount' => 10000,
        'tax_amount' => 0,
        'total_amount' => 10000,
        'adults' => 1,
        'children' => 0,
        'notes' => 'Test activity creation',
        'source' => 'direct',
        'expected_arrival_time' => '14:00',
    ])->assertRedirect();

    $reservation = Reservation::query()->where('code', 'RES-NEW-ACT')->first();
    expect($reservation)->not->toBeNull();

    $activity = Activity::query()
        ->where('log_name', 'reservation')
        ->where('subject_id', (string) $reservation->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['reservation_code'])->toBe('RES-NEW-ACT');
    expect($activity->properties['to_status'])->toBe(Reservation::STATUS_CONFIRMED);
    expect($activity->properties['room_number'] ?? null)->not->toBeNull();
});

it('logs activity when a reservation is updated', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'user' => $user,
    ] = setupReservationEnvironment('reservation-update-log');

    $user->assignRole('owner');

    actingAs($user);

    $newTotal = $reservation->total_amount + 5000;

    put(sprintf('http://%s/reservations/%s', tenantDomain($tenant), $reservation->id), [
        'code' => $reservation->code,
        'guest_id' => $reservation->guest_id,
        'room_type_id' => $reservation->room_type_id,
        'room_id' => $reservation->room_id,
        'offer_id' => $reservation->offer_id,
        'check_in_date' => $reservation->check_in_date->toDateString(),
        'check_out_date' => $reservation->check_out_date->toDateString(),
        'currency' => $reservation->currency,
        'unit_price' => $reservation->unit_price,
        'base_amount' => $reservation->base_amount,
        'tax_amount' => $reservation->tax_amount,
        'total_amount' => $newTotal,
        'adults' => $reservation->adults,
        'children' => $reservation->children,
        'notes' => 'Updated total',
        'source' => $reservation->source,
        'expected_arrival_time' => $reservation->expected_arrival_time,
    ])->assertRedirect();

    $activity = Activity::query()
        ->where('log_name', 'reservation')
        ->where('subject_id', (string) $reservation->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['reservation_code'])->toBe($reservation->code);
    expect((float) $activity->properties['changes']['total_amount']['to'])->toBe((float) $newTotal);
    expect($activity->properties['room_number'] ?? null)->not->toBeNull();
});

it('adds the room number to the checkout activity summary', function (): void {
    [
        'reservation' => $reservation,
        'user' => $user,
        'room' => $room,
    ] = setupReservationEnvironment('reservation-checkout-log');

    $user->assignRole('owner');

    $reservation->update([
        'status' => Reservation::STATUS_IN_HOUSE,
        'actual_check_in_at' => Carbon::parse('2025-05-01 15:00:00'),
        'check_in_date' => Carbon::parse('2025-05-01 15:00:00'),
        'check_out_date' => Carbon::parse('2025-05-02 12:00:00'),
    ]);

    actingAs($user);

    $stateMachine = app(ReservationStateMachine::class);
    $stateMachine->checkOut($reservation->fresh(), Carbon::parse('2025-05-02 12:00:00'));

    $activity = Activity::query()
        ->where('log_name', 'reservation')
        ->where('subject_id', (string) $reservation->id)
        ->where('event', 'checked_out')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['room_number'] ?? null)->toBe($room->number);

    $formatted = ActivityFormatter::format($activity);

    expect($formatted['sentence_fr'] ?? '')
        ->toContain('check-out')
        ->toContain($room->number);
});
