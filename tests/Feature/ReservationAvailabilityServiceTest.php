<?php

require_once __DIR__.'/FolioTestHelpers.php';

use App\Models\MaintenanceTicket;
use App\Models\Reservation;
use App\Services\ReservationAvailabilityService;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config([
        'app.url' => 'http://serena.test',
        'app.url_host' => 'serena.test',
        'app.url_scheme' => 'http',
        'tenancy.central_domains' => ['serena.test'],
    ]);
});

it('detects overlapping reservations on the same room', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $existingReservation,
        'room' => $room,
    ] = setupReservationEnvironment('room-conflict');

    $existingReservation->update([
        'room_id' => $room->id,
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-01-01',
        'check_out_date' => '2025-01-05',
    ]);

    $service = app(ReservationAvailabilityService::class);

    $data = [
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $existingReservation->room_type_id,
        'room_id' => $room->id,
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-01-04',
        'check_out_date' => '2025-01-07',
    ];

    try {
        $service->ensureAvailable($data);
        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('room_id');
    }
});

it('detects when room type capacity is exceeded', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $existingReservation,
    ] = setupReservationEnvironment('type-capacity');

    $existingReservation->update([
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-02-01',
        'check_out_date' => '2025-02-05',
    ]);

    $service = app(ReservationAvailabilityService::class);

    $data = [
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $existingReservation->room_type_id,
        'room_id' => null,
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-02-02',
        'check_out_date' => '2025-02-04',
    ];

    try {
        $service->ensureAvailable($data);
        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('room_type_id');
    }
});

it('ignores the reservation currently being updated', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'reservation' => $reservation,
        'room' => $room,
    ] = setupReservationEnvironment('ignore-current');

    $reservation->update([
        'room_id' => $room->id,
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-03-10',
        'check_out_date' => '2025-03-12',
    ]);

    $service = app(ReservationAvailabilityService::class);

    $data = [
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $reservation->room_type_id,
        'room_id' => $room->id,
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-03-10',
        'check_out_date' => '2025-03-12',
    ];

    expect(fn () => $service->ensureAvailable($data, $reservation->id))
        ->not
        ->toThrow(ValidationException::class);
});

it('blocks rooms with open maintenance tickets that stop sales', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-blocked');

    MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_OPEN,
        'severity' => MaintenanceTicket::SEVERITY_HIGH,
        'blocks_sale' => true,
        'title' => 'Panne',
        'opened_at' => now(),
    ]);

    $service = app(ReservationAvailabilityService::class);

    $data = [
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $room->room_type_id,
        'room_id' => $room->id,
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-02-02',
        'check_out_date' => '2025-02-04',
    ];

    expect(fn () => $service->ensureAvailable($data))
        ->toThrow(ValidationException::class);
});

it('allows rooms with non-blocking maintenance tickets', function (): void {
    [
        'tenant' => $tenant,
        'hotel' => $hotel,
        'room' => $room,
        'user' => $user,
    ] = setupReservationEnvironment('maintenance-non-blocking');

    MaintenanceTicket::query()->create([
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_id' => $room->id,
        'reported_by_user_id' => $user->id,
        'status' => MaintenanceTicket::STATUS_OPEN,
        'severity' => MaintenanceTicket::SEVERITY_MEDIUM,
        'blocks_sale' => false,
        'title' => 'Bruit climatisation',
        'opened_at' => now(),
    ]);

    $service = app(ReservationAvailabilityService::class);

    $data = [
        'tenant_id' => $tenant->id,
        'hotel_id' => $hotel->id,
        'room_type_id' => $room->room_type_id,
        'room_id' => $room->id,
        'status' => Reservation::STATUS_CONFIRMED,
        'check_in_date' => '2025-02-02',
        'check_out_date' => '2025-02-04',
    ];

    expect(fn () => $service->ensureAvailable($data))
        ->not
        ->toThrow(ValidationException::class);
});
