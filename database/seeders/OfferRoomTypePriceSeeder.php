<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class OfferRoomTypePriceSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $offers = Offer::query()
                ->where('hotel_id', $hotel->id)
                ->get();

            $roomTypes = RoomType::query()
                ->where('hotel_id', $hotel->id)
                ->get();

            foreach ($offers as $offer) {
                foreach ($roomTypes as $roomType) {
                    $price = match ($offer->kind) {
                        'night' => 25000,
                        'short_stay' => 10000,
                        'full_day' => 30000,
                        'weekend' => 45000,
                        default => 20000,
                    };

                    OfferRoomTypePrice::query()->firstOrCreate(
                        [
                            'tenant_id' => $hotel->tenant_id,
                            'hotel_id' => $hotel->id,
                            'offer_id' => $offer->id,
                            'room_type_id' => $roomType->id,
                        ],
                        [
                            'currency' => $hotel->currency ?? 'XAF',
                            'price' => $price,
                            'is_active' => true,
                        ],
                    );
                }
            }
        });
    }
}
