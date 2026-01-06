<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $roomTypes = RoomType::query()
                ->where('hotel_id', $hotel->id)
                ->get();

            foreach ($roomTypes as $roomType) {
                $roomCount = 5;

                for ($index = 1; $index <= $roomCount; $index++) {
                    Room::query()->firstOrCreate(
                        [
                            'tenant_id' => $hotel->tenant_id,
                            'hotel_id' => $hotel->id,
                            'room_type_id' => $roomType->id,
                            'number' => sprintf('%s-%02d', $roomType->id, $index),
                        ],
                        [
                            'status' => 'active',
                            'hk_status' => Room::HK_STATUS_INSPECTED,
                            'floor' => null,
                        ],
                    );
                }
            }
        });
    }
}
