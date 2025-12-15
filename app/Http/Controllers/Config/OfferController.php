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

class OfferController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('offers.view');

        $offers = Offer::query()
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(function (Offer $offer) use ($request) {
                $prices = OfferRoomTypePrice::query()
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->where('offer_id', $offer->id)
                    ->get(['room_type_id', 'price']);

                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'kind' => $offer->kind,
                    'billing_mode' => $offer->billing_mode,
                    'fixed_duration_hours' => $offer->fixed_duration_hours,
                    'check_in_from' => $offer->check_in_from,
                    'check_out_until' => $offer->check_out_until,
                    'valid_from' => $offer->valid_from,
                    'valid_to' => $offer->valid_to,
                    'valid_days_of_week' => $offer->valid_days_of_week,
                    'time_rule' => $offer->time_rule,
                    'time_config' => $offer->time_config,
                    'description' => $offer->description,
                    'is_active' => $offer->is_active,
                    'prices' => $prices,
                ];
            });

        $roomTypes = RoomType::query()
            ->when($this->activeHotelId($request), fn ($q) => $q->where('hotel_id', $this->activeHotelId($request)))
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Config/Offers/OffersIndex', [
            'offers' => $offers,
            'kindOptions' => ['hourly', 'night', 'day', 'package'],
            'billingModes' => ['fixed', 'per_night', 'per_hour'],
            'dayOptions' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'roomTypes' => $roomTypes,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('offers.create');

        return Inertia::render('Config/Offers/OffersCreate');
    }

    public function store(Request $request)
    {
        $this->authorize('offers.create');

        $data = $request->validate([
            'name' => ['required', 'string'],
            'kind' => ['required', 'string', 'in:hourly,night,day,package'],
            'billing_mode' => ['required', 'string', 'in:fixed,per_night,per_hour'],
            'fixed_duration_hours' => ['nullable', 'integer', 'min:1'],
            'check_in_from' => ['nullable', 'date_format:H:i'],
            'check_out_until' => ['nullable', 'date_format:H:i'],
            'time_rule' => ['nullable', 'string', 'in:rolling,fixed_window,fixed_checkout,weekend_window'],
            'time_config' => ['nullable', 'array'],
            'valid_days_of_week' => ['nullable', 'array'],
            'valid_days_of_week.*' => ['integer', 'between:1,7'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'prices' => ['nullable', 'array'],
            'prices.*.room_type_id' => ['required_with:prices', 'integer', 'exists:room_types,id'],
            'prices.*.price' => ['required_with:prices', 'numeric', 'min:0'],
        ]);

        $hotel = Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($this->activeHotelId($request), fn ($q) => $q->where('id', $this->activeHotelId($request)))
            ->firstOrFail();

        $offer = Offer::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
        ]);
        foreach ($data['prices'] ?? [] as $price) {
            OfferRoomTypePrice::query()->updateOrCreate(
                [
                    'tenant_id' => $offer->tenant_id,
                    'hotel_id' => $offer->hotel_id,
                    'offer_id' => $offer->id,
                    'room_type_id' => $price['room_type_id'],
                ],
                [
                    'currency' => $hotel->currency ?? 'XAF',
                    'price' => $price['price'],
                    'is_active' => true,
                ],
            );
        }

        return redirect()->route('ressources.offers.index')->with('success', 'Offre créée.');
    }

    public function edit(Request $request, int $id): Response
    {
        $this->authorize('offers.update');

        $offer = Offer::query()
            ->where('hotel_id', $this->activeHotelId($request))
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return Inertia::render('Config/Offers/OffersEdit', [
            'offer' => $offer,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('offers.update');

        $data = $request->validate([
            'name' => ['required', 'string'],
            'kind' => ['required', 'string', 'in:hourly,night,day,package'],
            'billing_mode' => ['required', 'string', 'in:fixed,per_night,per_hour'],
            'fixed_duration_hours' => ['nullable', 'integer', 'min:1'],
            'check_in_from' => ['nullable', 'date_format:H:i'],
            'check_out_until' => ['nullable', 'date_format:H:i'],
            'time_rule' => ['nullable', 'string', 'in:rolling,fixed_window,fixed_checkout,weekend_window'],
            'time_config' => ['nullable', 'array'],
            'valid_days_of_week' => ['nullable', 'array'],
            'valid_days_of_week.*' => ['integer', 'between:1,7'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'prices' => ['nullable', 'array'],
            'prices.*.room_type_id' => ['required_with:prices', 'integer', 'exists:room_types,id'],
            'prices.*.price' => ['required_with:prices', 'numeric', 'min:0'],
        ]);

        $offer = Offer::query()
            ->where('hotel_id', $this->activeHotelId($request))
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $offer->update($data);

        if (! empty($data['prices'])) {
            $hotel = Hotel::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->when($this->activeHotelId($request), fn ($q) => $q->where('id', $this->activeHotelId($request)))
                ->firstOrFail();

            foreach ($data['prices'] as $price) {
                OfferRoomTypePrice::updateOrCreate(
                    [
                        'tenant_id' => $offer->tenant_id,
                        'hotel_id' => $offer->hotel_id,
                        'offer_id' => $offer->id,
                        'room_type_id' => $price['room_type_id'],
                    ],
                    [
                        'currency' => $hotel->currency ?? 'XAF',
                        'price' => $price['price'],
                        'is_active' => true,
                    ],
                );
            }
        }

        return redirect()->route('ressources.offers.index')->with('success', 'Offre mise à jour.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->authorize('offers.delete');

        $offer = Offer::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $offer->delete();

        return redirect()->route('ressources.offers.index')->with('success', 'Offre supprimée.');
    }
}
