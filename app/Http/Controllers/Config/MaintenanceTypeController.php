<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaintenanceTypeRequest;
use App\Http\Requests\UpdateMaintenanceTypeRequest;
use App\Models\MaintenanceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceTypeController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('maintenance.types.manage');

        $hotelId = $this->activeHotelId($request);

        $types = MaintenanceType::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderBy('name')
            ->get()
            ->map(fn (MaintenanceType $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'is_active' => (bool) $type->is_active,
            ]);

        return Inertia::render('Config/MaintenanceTypes/MaintenanceTypesIndex', [
            'maintenanceTypes' => $types,
        ]);
    }

    public function store(StoreMaintenanceTypeRequest $request): RedirectResponse
    {
        $this->authorize('maintenance.types.manage');

        $user = $request->user();
        $hotelId = $this->activeHotelId($request);

        $data = $request->validated();

        MaintenanceType::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotelId,
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('ressources.maintenance-types.index')
            ->with('success', 'Type de maintenance créé.');
    }

    public function update(UpdateMaintenanceTypeRequest $request, MaintenanceType $maintenanceType): RedirectResponse
    {
        $this->authorize('maintenance.types.manage');

        $hotelId = $this->activeHotelId($request);

        abort_if($maintenanceType->tenant_id !== $request->user()->tenant_id, 404);
        abort_if((int) $maintenanceType->hotel_id !== (int) $hotelId, 404);

        $data = $request->validated();

        $maintenanceType->update([
            'name' => $data['name'],
            'is_active' => (bool) ($data['is_active'] ?? $maintenanceType->is_active),
        ]);

        return redirect()
            ->route('ressources.maintenance-types.index')
            ->with('success', 'Type de maintenance mis à jour.');
    }
}
