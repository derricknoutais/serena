<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBarTableRequest;
use App\Http\Requests\UpdateBarTableRequest;
use App\Models\BarTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BarTableConfigController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('pos.tables.manage');

        $hotelId = $this->activeHotelId($request);

        $tables = BarTable::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (BarTable $table) => [
                'id' => $table->id,
                'name' => $table->name,
                'area' => $table->area,
                'capacity' => $table->capacity,
                'sort_order' => $table->sort_order,
                'is_active' => (bool) $table->is_active,
            ]);

        return Inertia::render('Config/BarTables/BarTablesIndex', [
            'tables' => $tables,
        ]);
    }

    public function store(StoreBarTableRequest $request): RedirectResponse
    {
        $this->authorize('pos.tables.manage');

        $user = $request->user();
        $hotelId = $this->activeHotelId($request);
        $data = $request->validated();

        BarTable::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotelId,
            'name' => $data['name'],
            'area' => $data['area'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('ressources.bar-tables.index')
            ->with('success', 'Table enregistrée.');
    }

    public function update(UpdateBarTableRequest $request, BarTable $barTable): RedirectResponse
    {
        $this->authorize('pos.tables.manage');

        $hotelId = $this->activeHotelId($request);

        abort_if($barTable->tenant_id !== $request->user()->tenant_id, 404);
        abort_if((int) $barTable->hotel_id !== (int) $hotelId, 404);

        $data = $request->validated();

        $barTable->update([
            'name' => $data['name'],
            'area' => $data['area'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('ressources.bar-tables.index')
            ->with('success', 'Table mise à jour.');
    }
}
