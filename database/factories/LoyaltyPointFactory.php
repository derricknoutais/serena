<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoyaltyPoint>
 */
class LoyaltyPointFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reservation = $this->resolveReservation();

        return [
            'tenant_id' => $reservation->tenant_id,
            'hotel_id' => $reservation->hotel_id,
            'reservation_id' => $reservation->id,
            'guest_id' => $reservation->guest_id,
            'type' => 'earn',
            'points' => 50,
        ];
    }

    private function resolveReservation(): Reservation
    {
        $reservation = Reservation::query()->first();

        if ($reservation) {
            return $reservation;
        }

        $tenantId = (string) Str::uuid();
        $tenantSlug = Str::slug(Str::random(8));

        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Test Tenant',
            'slug' => $tenantSlug,
            'plan' => 'standard',
            'contact_email' => 'test@example.com',
            'data' => ['name' => 'Test Tenant', 'slug' => $tenantSlug],
        ]);

        $hotel = Hotel::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Hotel Test',
            'currency' => 'XAF',
            'timezone' => 'Africa/Douala',
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
            'number' => 'RM-TEST',
            'floor' => '1',
            'status' => 'active',
            'hk_status' => Room::HK_STATUS_INSPECTED,
        ]);

        $guest = Guest::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'guest@example.com',
            'phone' => '123456789',
            'document_number' => 'ID-TEST',
            'address' => 'Main street',
        ]);

        return Reservation::query()->create([
            'tenant_id' => $tenant->id,
            'hotel_id' => $hotel->id,
            'guest_id' => $guest->id,
            'room_type_id' => $roomType->id,
            'room_id' => $room->id,
            'offer_id' => null,
            'code' => 'RES-TEST',
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
    }
}
