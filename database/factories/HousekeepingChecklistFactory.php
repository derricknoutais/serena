<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\HousekeepingChecklist;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HousekeepingChecklist>
 */
class HousekeepingChecklistFactory extends Factory
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
            'name' => 'Checklist '.fake()->word(),
            'scope' => HousekeepingChecklist::SCOPE_GLOBAL,
            'room_type_id' => null,
            'is_active' => false,
        ];
    }

    private function resolveHotel(): Hotel
    {
        $hotel = Hotel::query()->first();

        if ($hotel !== null) {
            return $hotel;
        }

        $tenantId = (string) Str::uuid();

        Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Housekeeping Tenant',
            'slug' => Str::slug(Str::random(8)),
            'plan' => 'standard',
        ]);

        $hotel = Hotel::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Housekeeping Hotel',
            'code' => Str::upper(Str::random(3)),
            'currency' => 'XAF',
            'timezone' => 'Africa/Douala',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
        ]);

        RoomType::query()->create([
            'tenant_id' => $tenantId,
            'hotel_id' => $hotel->id,
            'name' => 'Standard',
            'code' => Str::upper(Str::random(3)),
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'base_price' => 10000,
        ]);

        return $hotel;
    }
}
