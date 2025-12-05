<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveHotelController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $hotel = Hotel::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->findOrFail($request->integer('hotel_id'));

        $user->forceFill(['active_hotel_id' => $hotel->id])->save();
        $request->session()->put('active_hotel_id', $hotel->id);
        $request->session()->flash('hotel_notice', 'Toutes vos actions sont désormais liées à l’hôtel "'.$hotel->name.'".');

        return redirect()
            ->route('dashboard')
            ->with('success', 'Vous avez basculé sur l’hôtel '.$hotel->name.'. Toutes les actions seront liées à cet hôtel.');
    }
}
