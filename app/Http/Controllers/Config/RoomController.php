<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $activeHotelId = $this->activeHotelId($request);

        $rooms = Room::query()
            ->with('roomType')
            ->when($activeHotelId, fn ($q) => $q->where('hotel_id', $activeHotelId))
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('number')
            ->paginate(15)
            ->through(fn (Room $room) => [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'status' => $room->status,
                'hk_status' => $room->hk_status,
                'room_type_id' => $room->room_type_id,
                'room_type' => $room->roomType?->name,
            ]);

        $roomTypes = RoomType::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($activeHotelId, fn ($q) => $q->where('hotel_id', $activeHotelId))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Config/Rooms/RoomsIndex', [
            'rooms' => $rooms,
            'roomTypes' => $roomTypes,
            'statuses' => ['active', 'inactive', 'out_of_order'],
            'housekeepingStatuses' => ['clean', 'dirty', 'inspected'],
        ]);
    }

    public function create(Request $request): Response
    {
        $roomTypes = RoomType::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Config/Rooms/RoomsCreate', [
            'roomTypes' => $roomTypes,
            'statuses' => ['active', 'inactive', 'out_of_order'],
            'housekeepingStatuses' => ['clean', 'dirty', 'inspected'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'room_type_id' => $request->integer('room_type_id'),
            'status' => (string) $request->input('status'),
            'hk_status' => (string) $request->input('hk_status'),
        ]);

        $data = $request->validate([
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'number' => ['required', 'string'],
            'floor' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,inactive,out_of_order'],
            'hk_status' => ['required', 'string', 'in:clean,dirty,inspected'],
        ]);

        $hotelId = $this->activeHotelId($request);
        $hotel = Hotel::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($q) => $q->where('id', $hotelId))
            ->firstOrFail();

        Room::query()->create([
            ...$data,
            'tenant_id' => $request->user()->tenant_id,
            'hotel_id' => $hotel->id,
        ]);

        return redirect()->route('ressources.rooms.index')->with('success', 'Chambre créée.');
    }

    public function edit(Request $request, int $id): Response
    {
        $room = Room::query()
            ->where('hotel_id', $this->activeHotelId($request))
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $roomTypes = RoomType::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('hotel_id', $room->hotel_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Config/Rooms/RoomsEdit', [
            'room' => $room,
            'roomTypes' => $roomTypes,
            'statuses' => ['active', 'inactive', 'out_of_order'],
            'housekeepingStatuses' => ['clean', 'dirty', 'inspected'],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $request->merge([
            'room_type_id' => $request->integer('room_type_id'),
            'status' => (string) $request->input('status'),
            'hk_status' => (string) $request->input('hk_status'),
        ]);

        $data = $request->validate([
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'number' => ['required', 'string'],
            'floor' => ['nullable', 'string'],
            'status' => ['required', 'string'],
            'hk_status' => ['required', 'string'],
        ]);

        $room = Room::query()
            ->where('hotel_id', $this->activeHotelId($request))
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $room->update($data);

        return redirect()->route('ressources.rooms.index')->with('success', 'Chambre mise à jour.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $room = Room::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $room->delete();

        return redirect()->route('ressources.rooms.index')->with('success', 'Chambre supprimée.');
    }
}
