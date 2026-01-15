<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\StockOnHand;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockLocationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('stock.locations.manage');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->resolveHotel($user);

        $locations = StorageLocation::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->orderBy('name')
            ->get();

        $stockOnHand = StockOnHand::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->with('stockItem')
            ->get()
            ->groupBy('storage_location_id');

        $payload = $locations->map(fn (StorageLocation $location) => [
            'id' => $location->id,
            'name' => $location->name,
            'category' => $location->category,
            'count' => ($stockOnHand[$location->id]?->count()) ?? 0,
        ]);

        return Inertia::render('Stock/Locations/Index', [
            'locations' => $payload,
            'records' => collect($stockOnHand)->mapWithKeys(fn ($records, $locationId) => [
                (int) $locationId => $records->map(fn ($record) => [
                    'id' => $record->id,
                    'stock_item' => $record->stockItem?->only(['id', 'name', 'sku', 'unit']),
                    'quantity_on_hand' => (float) $record->quantity_on_hand,
                ])->values()->toArray(),
            ])->toArray(),
        ]);
    }

    public function show(Request $request, StorageLocation $storageLocation): Response
    {
        $this->authorize('stock.locations.manage');
        $user = $request->user();
        $hotel = $this->resolveHotel($user);

        if ($storageLocation->tenant_id !== $user->tenant_id || (int) $storageLocation->hotel_id !== $hotel->id) {
            abort(404);
        }

        $records = StockOnHand::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->where('storage_location_id', $storageLocation->id)
            ->with('stockItem')
            ->orderByDesc('quantity_on_hand')
            ->get();

        return Inertia::render('Stock/Locations/Show', [
            'location' => $storageLocation->only(['id', 'name', 'category']),
            'records' => $records->map(fn ($record) => [
                'id' => $record->id,
                'stock_item' => $record->stockItem?->only(['id', 'name', 'sku', 'unit']),
                'quantity_on_hand' => (float) $record->quantity_on_hand,
            ])->values()->toArray(),
        ]);
    }

    private function resolveHotel(?User $user): Hotel
    {
        $hotelId = (int) ($user->active_hotel_id ?? $user->hotel_id ?? 0);

        if ($hotelId === 0) {
            abort(404, 'Aucun hôtel actif.');
        }

        return Hotel::query()
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($hotelId);
    }
}
