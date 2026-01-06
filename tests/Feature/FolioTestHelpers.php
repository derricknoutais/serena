<?php

use App\Models\Folio;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

if (! function_exists('setupReservationEnvironment')) {
    /**
     * @return array{
     *     tenant: Tenant,
     *     hotel: Hotel,
     *     roomType: RoomType,
     *     guest: Guest,
     *     reservation: Reservation,
     *     user: User,
     *     folio: ?Folio,
     *     methods: array<int, PaymentMethod>,
     * }
     */
    function setupReservationEnvironment(string $slug): array
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant '.ucfirst($slug),
            'slug' => $slug,
            'plan' => 'standard',
        ]);

        $tenant->createDomain(['domain' => sprintf('%s.serena.test', $slug)]);

        tenancy()->initialize($tenant);

        $hotel = Hotel::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Hotel '.ucfirst($slug),
            'code' => strtoupper(substr($slug, 0, 3)).'1',
            'currency' => 'XAF',
            'timezone' => 'Africa/Douala',
            'address' => 'Main street',
            'city' => 'Douala',
            'country' => 'CM',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
        ]);

        $roomType = RoomType::query()->create([
            'tenant_id' => $tenant->id,
            'hotel_id' => $hotel->id,
            'name' => 'Deluxe',
            'code' => 'DLX',
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'base_price' => 10000,
            'description' => 'Deluxe room',
        ]);

        $room = Room::query()->create([
            'tenant_id' => $tenant->id,
            'hotel_id' => $hotel->id,
            'room_type_id' => $roomType->id,
            'number' => sprintf('RM-%s', Str::upper(Str::random(4))),
            'floor' => '1',
            'status' => 'active',
            'hk_status' => Room::HK_STATUS_INSPECTED,
        ]);

        $guest = Guest::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => sprintf('guest-%s@example.com', $slug),
            'phone' => '123456789',
            'document_number' => 'ID-'.$slug,
            'address' => 'Main street',
        ]);

        $reservation = Reservation::query()->create([
            'tenant_id' => $tenant->id,
            'hotel_id' => $hotel->id,
            'guest_id' => $guest->id,
            'room_type_id' => $roomType->id,
            'room_id' => $room->id,
            'offer_id' => null,
            'code' => 'RES-'.strtoupper($slug),
            'status' => Reservation::STATUS_CONFIRMED,
            'source' => 'direct',
            'offer_name' => null,
            'offer_kind' => null,
            'adults' => 2,
            'children' => 0,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->copy()->addDay()->toDateString(),
            'expected_arrival_time' => '14:00',
            'actual_check_in_at' => null,
            'actual_check_out_at' => null,
            'currency' => $hotel->currency,
            'unit_price' => 10000,
            'base_amount' => 10000,
            'tax_amount' => 0,
            'total_amount' => 10000,
            'notes' => null,
            'booked_by_user_id' => null,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'active_hotel_id' => $hotel->id,
            'email' => sprintf('agent-%s@example.com', $slug),
            'email_verified_at' => now(),
        ]);

        $user->hotels()->attach($hotel);

        $methods = [
            PaymentMethod::query()->create([
                'tenant_id' => $tenant->id,
                'hotel_id' => $hotel->id,
                'name' => 'Cash',
                'code' => 'CASH',
                'type' => 'cash',
                'is_active' => true,
            ]),
        ];

        return compact('tenant', 'hotel', 'roomType', 'guest', 'reservation', 'user', 'room') + [
            'folio' => null,
            'methods' => $methods,
        ];
    }
}

if (! function_exists('tenantDomain')) {
    function tenantDomain(Tenant $tenant): string
    {
        return (string) $tenant->domains()->value('domain');
    }
}
