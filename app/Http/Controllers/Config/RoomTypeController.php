<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Offer;
use App\Models\OfferRoomTypePrice;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomTypeController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $activeHotelId = $this->activeHotelId($request);

        $roomTypes = RoomType::query()
            ->when($activeHotelId, fn ($q) => $q->where('hotel_id', $activeHotelId))
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (RoomType $roomType) => [
                'id' => $roomType->id,
                'name' => $roomType->name,
                'capacity_adults' => $roomType->capacity_adults,
                'capacity_children' => $roomType->capacity_children,
                'base_price' => $roomType->base_price,
                'description' => $roomType->description,
            ]);

        return Inertia::render('Config/RoomTypes/RoomTypesIndex', [
            'roomTypes' => $roomTypes,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Config/RoomTypes/RoomTypesCreate');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'capacity_adults' => ['required', 'integer', 'min:1'],
            'capacity_children' => ['required', 'integer', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $hotelId = $this->activeHotelId($request);
        $hotel = Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('id', $hotelId))
            ->firstOrFail();

        RoomType::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
        ]);

        return redirect()->route('ressources.room-types.index')->with('success', 'Type de chambre créé.');
    }

    public function edit(Request $request, int $id): Response
    {
        $roomType = RoomType::query()
            ->where('hotel_id', $this->activeHotelId($request))
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return Inertia::render('Config/RoomTypes/RoomTypesEdit', [
            'roomType' => $roomType,
        ]);
    }

    public function show(Request $request, int $id): Response|RedirectResponse
    {
        $roomType = RoomType::query()
            ->with(['offerPrices.offer'])
            ->where('hotel_id', $this->activeHotelId($request))
            ->where('tenant_id', $request->user()->tenant_id)
            ->find($id);

        if ($roomType === null) {
            return redirect()
                ->route('ressources.room-types.index')
                ->with('warning', 'Ce type de chambre n’existe pas pour l’hôtel actif.');
        }

        $offers = Offer::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('hotel_id', $this->activeHotelId($request))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $prices = $roomType->offerPrices->map(fn (OfferRoomTypePrice $price) => [
            'id' => $price->id,
            'offer_name' => $price->offer?->name,
            'price' => $price->price,
            'extra_adult_price' => $price->extra_adult_price,
            'extra_child_price' => $price->extra_child_price,
            'is_active' => $price->is_active,
        ]);

        return Inertia::render('Config/RoomTypes/RoomTypesShow', [
            'roomType' => $roomType->only(['id', 'name', 'capacity_adults', 'capacity_children', 'base_price', 'description']),
            'offers' => $offers,
            'prices' => $prices,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'capacity_adults' => ['required', 'integer', 'min:1'],
            'capacity_children' => ['required', 'integer', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $roomType = RoomType::query()
            ->where('hotel_id', $this->activeHotelId($request))
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $roomType->update($data);

        return redirect()->route('ressources.room-types.index')->with('success', 'Type de chambre mis à jour.');
    }

    public function storePrice(Request $request, RoomType $roomType): RedirectResponse
    {
        $roomType = RoomType::query()
            ->where('id', $roomType->getKey())
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('hotel_id', $this->activeHotelId($request))
            ->firstOrFail();

        $data = $request->validate([
            'offer_id' => ['required', 'integer', 'exists:offers,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'extra_adult_price' => ['nullable', 'numeric', 'min:0'],
            'extra_child_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $offer = Offer::query()
            ->where('id', $data['offer_id'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('hotel_id', $this->activeHotelId($request))
            ->firstOrFail();

        OfferRoomTypePrice::query()->updateOrCreate(
            [
                'offer_id' => $offer->id,
                'room_type_id' => $roomType->id,
            ],
            [
                ...$data,
                'tenant_id' => $request->user()->tenant_id,
                'hotel_id' => $this->activeHotelId($request),
                'is_active' => $data['is_active'] ?? true,
            ],
        );

        return redirect()
            ->route('ressources.room-types.show', $roomType)
            ->with('success', 'Tarif enregistré pour ce type de chambre.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $roomType = RoomType::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $roomType->delete();

        return redirect()->route('ressources.room-types.index')->with('success', 'Type de chambre supprimé.');
    }
}
