<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotelConfigController extends Controller
{
    public function edit(Request $request): Response
    {
        $hotel = Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        return Inertia::render('Config/HotelConfig', [
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
            'code' => ['required', 'string', 'max:10'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string'],
            'check_in_time' => ['required'],
            'check_out_time' => ['required'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
        ]);

        $hotel = Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        $hotel->update($data);

        return redirect()
            ->route('ressources.hotel.edit')
            ->with('success', 'Informations de l’hôtel mises à jour.');
    }
}
