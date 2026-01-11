<?php

namespace Database\Seeders;

use App\Models\HousekeepingTask;
use App\Models\Room;
use Illuminate\Database\Seeder;

class HousekeepingTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Room::query()
            ->where('hk_status', 'dirty')
            ->each(function (Room $room): void {
                HousekeepingTask::query()->firstOrCreate(
                    [
                        'tenant_id' => $room->tenant_id,
                        'hotel_id' => $room->hotel_id,
                        'room_id' => $room->id,
                        'type' => HousekeepingTask::TYPE_CLEANING,
                        'status' => HousekeepingTask::STATUS_PENDING,
                    ],
                    [
                        'priority' => HousekeepingTask::PRIORITY_NORMAL,
                        'created_from' => HousekeepingTask::SOURCE_RECEPTION,
                    ],
                );
            });
    }
}
