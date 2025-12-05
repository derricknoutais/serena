<?php

namespace App\Http\Controllers\Config\Concerns;

use App\Models\Hotel;
use Illuminate\Http\Request;

trait ResolvesActiveHotel
{
    protected function activeHotelId(Request $request): ?int
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $hotelId = $request->session()->get('active_hotel_id', $user->active_hotel_id);

        if ($hotelId !== null) {
            $belongs = $user->hotels()->where('hotels.id', $hotelId)->exists();

            if (! $belongs) {
                $hotelId = null;
            }
        }

        if ($hotelId === null) {
            $first = $user->hotels()->first();
            if ($first) {
                $hotelId = $first->id;
                $user->forceFill(['active_hotel_id' => $hotelId])->save();
                $request->session()->put('active_hotel_id', $hotelId);
            }
        }

        return $hotelId;
    }

    protected function activeHotel(Request $request): ?Hotel
    {
        $hotelId = $this->activeHotelId($request);

        if ($hotelId === null) {
            return null;
        }

        return Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->find($hotelId);
    }
}
