<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStorageLocationRequest;
use App\Http\Requests\UpdateStorageLocationRequest;
use App\Models\StorageLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorageLocationController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('stock.locations.manage');

        $hotelId = $this->activeHotelId($request);

        $locations = StorageLocation::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderBy('name')
            ->get()
            ->map(fn (StorageLocation $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'category' => $location->category,
                'is_active' => (bool) $location->is_active,
            ]);

        return Inertia::render('Config/StorageLocations/StorageLocationsIndex', [
            'locations' => $locations,
        ]);
    }

    public function store(StoreStorageLocationRequest $request): RedirectResponse
    {
        $this->authorize('stock.locations.manage');

        $user = $request->user();
        $hotelId = $this->activeHotelId($request);
        $data = $request->validated();

        StorageLocation::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotelId,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'category' => $data['category'] ?? 'general',
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('ressources.storage-locations.index')->with('success', 'Emplacement enregistré.');
    }

    public function update(UpdateStorageLocationRequest $request, StorageLocation $storageLocation): RedirectResponse
    {
        $this->authorize('stock.locations.manage');

        $hotelId = $this->activeHotelId($request);

        abort_if($storageLocation->tenant_id !== $request->user()->tenant_id, 404);
        abort_if((int) $storageLocation->hotel_id !== (int) $hotelId, 404);

        $data = $request->validated();

        $storageLocation->update([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'category' => $data['category'] ?? $storageLocation->category,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('ressources.storage-locations.index')->with('success', 'Emplacement mis à jour.');
    }
}
