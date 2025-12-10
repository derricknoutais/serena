<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotelConfigController extends Controller
{
    use ResolvesActiveHotel;

    public function edit(Request $request): Response
    {
        $hotel = $this->activeHotel($request);

        if ($hotel === null) {
            $hotel = Hotel::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->first();

            if ($hotel !== null) {
                $request->user()->forceFill(['active_hotel_id' => $hotel->id])->save();
                $request->user()->hotels()->syncWithoutDetaching([$hotel->id]);
                $request->session()->put('active_hotel_id', $hotel->id);
            }
        }

        return Inertia::render('Config/Hotel/HotelIndex', [
            'hotel' => $hotel,
            'flash' => [
                'success' => session('success'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['string', 'nullable'],
            'check_in_time' => ['required'],
            'check_out_time' => ['required'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
        ]);

        $hotel = $this->activeHotel($request);

        if ($hotel === null) {
            $hotel = Hotel::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->firstOrCreate([
                    'tenant_id' => $request->user()->tenant_id,
                    'name' => $data['name'],
                ], $data);
        } else {
            $hotel->update($data);
        }

        $request->user()->forceFill(['active_hotel_id' => $hotel->id])->save();
        $request->user()->hotels()->syncWithoutDetaching([$hotel->id]);
        $request->session()->put('active_hotel_id', $hotel->id);

        return redirect()
            ->route('ressources.hotel.edit')
            ->with('success', 'Informations de l’hôtel mises à jour.');
    }
}
