<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHotelLoyaltySettingsRequest;
use App\Models\HotelLoyaltySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyController extends Controller
{
    use ResolvesActiveHotel;

    public function edit(Request $request): Response
    {
        $hotel = $this->activeHotel($request);

        if (! $hotel) {
            abort(404, 'Aucun hôtel actif.');
        }

        $settings = $hotel->loyaltySetting;

        return Inertia::render('settings/Loyalty', [
            'hotel' => $hotel->only(['id', 'name', 'currency']),
            'settings' => [
                'enabled' => $settings?->enabled ?? false,
                'earning_mode' => $settings?->earning_mode ?? 'amount',
                'points_per_amount' => $settings?->points_per_amount,
                'amount_base' => $settings?->amount_base,
                'points_per_night' => $settings?->points_per_night,
                'fixed_points' => $settings?->fixed_points,
                'max_points_per_stay' => $settings?->max_points_per_stay,
            ],
        ]);
    }

    public function update(UpdateHotelLoyaltySettingsRequest $request): RedirectResponse
    {
        $hotel = $this->activeHotel($request);

        if (! $hotel) {
            abort(404, 'Aucun hôtel actif.');
        }

        $data = $request->validated();

        $payload = [
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
            'enabled' => (bool) $data['enabled'],
            'earning_mode' => $data['earning_mode'],
            'points_per_amount' => null,
            'amount_base' => null,
            'points_per_night' => null,
            'fixed_points' => null,
            'max_points_per_stay' => $data['max_points_per_stay'] ?? null,
        ];

        if ($data['earning_mode'] === 'amount') {
            $payload['points_per_amount'] = $data['points_per_amount'];
            $payload['amount_base'] = $data['amount_base'];
        }

        if ($data['earning_mode'] === 'nights') {
            $payload['points_per_night'] = $data['points_per_night'];
        }

        if ($data['earning_mode'] === 'fixed') {
            $payload['fixed_points'] = $data['fixed_points'];
        }

        HotelLoyaltySetting::query()->updateOrCreate(
            [
                'tenant_id' => $request->user()->tenant_id,
                'hotel_id' => $hotel->id,
            ],
            $payload,
        );

        return back()->with('success', 'Paramètres de fidélité mis à jour.');
    }
}
