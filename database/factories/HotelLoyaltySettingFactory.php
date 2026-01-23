<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HotelLoyaltySetting>
 */
class HotelLoyaltySettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hotel = $this->resolveHotel();

        return [
            'tenant_id' => $hotel->tenant_id,
            'hotel_id' => $hotel->id,
            'enabled' => true,
            'earning_mode' => 'amount',
            'points_per_amount' => 1,
            'amount_base' => 1000,
            'points_per_night' => null,
            'fixed_points' => null,
            'max_points_per_stay' => null,
        ];
    }

    private function resolveHotel(): Hotel
    {
        $hotel = Hotel::query()->first();

        if ($hotel) {
            return $hotel;
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

        return Hotel::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Hotel Test',
            'currency' => 'XAF',
            'timezone' => 'Africa/Douala',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
        ]);
    }
}
