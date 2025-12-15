<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'early_policy' => ['nullable', 'string', Rule::in(['forbidden', 'free', 'paid'])],
            'early_fee_type' => ['nullable', 'string', Rule::in(['flat', 'percent'])],
            'early_fee_value' => ['nullable', 'numeric', 'min:0'],
            'early_cutoff_time' => ['nullable', 'string'],
            'late_policy' => ['nullable', 'string', Rule::in(['forbidden', 'free', 'paid'])],
            'late_fee_type' => ['nullable', 'string', Rule::in(['flat', 'percent'])],
            'late_fee_value' => ['nullable', 'numeric', 'min:0'],
            'late_max_time' => ['nullable', 'string'],
        ]);

        $staySettings = [
            'standard_checkin_time' => $data['check_in_time'] ?? null,
            'standard_checkout_time' => $data['check_out_time'] ?? null,
            'early_checkin' => [
                'policy' => $data['early_policy'] ?? 'free',
                'fee_type' => $data['early_fee_type'] ?? 'flat',
                'fee_value' => $data['early_fee_value'] ?? 0,
                'cutoff_time' => $data['early_cutoff_time'] ?? null,
            ],
            'late_checkout' => [
                'policy' => $data['late_policy'] ?? 'free',
                'fee_type' => $data['late_fee_type'] ?? 'flat',
                'fee_value' => $data['late_fee_value'] ?? 0,
                'max_time' => $data['late_max_time'] ?? null,
            ],
        ];

        $data['stay_settings'] = $staySettings;
        unset(
            $data['early_policy'],
            $data['early_fee_type'],
            $data['early_fee_value'],
            $data['early_cutoff_time'],
            $data['late_policy'],
            $data['late_fee_type'],
            $data['late_fee_value'],
            $data['late_max_time'],
        );

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
