<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $roomTypes = RoomType::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (RoomType $roomType) => [
                'id' => $roomType->id,
                'name' => $roomType->name,
                'code' => $roomType->code,
                'capacity_adults' => $roomType->capacity_adults,
                'capacity_children' => $roomType->capacity_children,
                'base_price' => $roomType->base_price,
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
            'code' => ['nullable', 'string'],
            'capacity_adults' => ['required', 'integer', 'min:1'],
            'capacity_children' => ['required', 'integer', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $hotel = Hotel::query()->where('tenant_id', $request->user()->tenant_id)->firstOrFail();

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
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        return Inertia::render('Config/RoomTypes/RoomTypesEdit', [
            'roomType' => $roomType,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'capacity_adults' => ['required', 'integer', 'min:1'],
            'capacity_children' => ['required', 'integer', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $roomType = RoomType::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $roomType->update($data);

        return redirect()->route('ressources.room-types.index')->with('success', 'Type de chambre mis à jour.');
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
