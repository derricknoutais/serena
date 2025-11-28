<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Offer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    public function index(Request $request): Response
    {
        $offers = Offer::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Offer $offer) => [
                'id' => $offer->id,
                'name' => $offer->name,
                'kind' => $offer->kind,
                'billing_mode' => $offer->billing_mode,
                'is_active' => $offer->is_active,
            ]);

        return Inertia::render('Config/Offers/OffersIndex', [
            'offers' => $offers,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Config/Offers/OffersCreate');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['required', 'string'],
            'kind' => ['required', 'string'],
            'billing_mode' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hotel = Hotel::query()->where('tenant_id', $request->user()->tenant_id)->firstOrFail();

        Offer::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
        ]);

        return redirect()->route('ressources.offers.index')->with('success', 'Offre créée.');
    }

    public function edit(Request $request, int $id): Response
    {
        $offer = Offer::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return Inertia::render('Config/Offers/OffersEdit', [
            'offer' => $offer,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['required', 'string'],
            'kind' => ['required', 'string'],
            'billing_mode' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $offer = Offer::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $offer->update($data);

        return redirect()->route('ressources.offers.index')->with('success', 'Offre mise à jour.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $offer = Offer::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $offer->delete();

        return redirect()->route('ressources.offers.index')->with('success', 'Offre supprimée.');
    }
}
