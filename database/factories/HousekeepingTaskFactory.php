<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\HousekeepingTask;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HousekeepingTask>
 */
class HousekeepingTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $room = $this->resolveRoom();

        return [
            'tenant_id' => $room->tenant_id,
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'type' => HousekeepingTask::TYPE_CLEANING,
            'status' => HousekeepingTask::STATUS_PENDING,
            'priority' => HousekeepingTask::PRIORITY_NORMAL,
            'created_from' => HousekeepingTask::SOURCE_RECEPTION,
            'started_at' => null,
            'ended_at' => null,
        ];
    }

    private function resolveRoom(): Room
    {
        $room = Room::query()->first();

        if ($room !== null) {
            return $room;
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

        $roomType = RoomType::query()->create([
            'tenant_id' => $tenantId,
            'hotel_id' => $hotel->id,
            'name' => 'Standard',
            'code' => Str::upper(Str::random(3)),
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'base_price' => 10000,
        ]);

        return Room::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'hotel_id' => $hotel->id,
            'room_type_id' => $roomType->id,
            'number' => Str::upper(Str::random(3)),
            'floor' => '1',
            'status' => Room::STATUS_AVAILABLE,
            'hk_status' => Room::HK_STATUS_INSPECTED,
        ]);
    }
}
