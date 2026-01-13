<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTechnicianRequest;
use App\Http\Requests\UpdateTechnicianRequest;
use App\Models\Technician;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TechnicianController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('maintenance.technicians.manage');

        $hotelId = $this->activeHotelId($request);

        $technicians = Technician::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderBy('name')
            ->get()
            ->map(fn (Technician $technician) => [
                'id' => $technician->id,
                'name' => $technician->name,
                'phone' => $technician->phone,
                'email' => $technician->email,
                'company_name' => $technician->company_name,
                'is_internal' => (bool) $technician->is_internal,
                'notes' => $technician->notes,
            ]);

        return Inertia::render('Config/Technicians/TechniciansIndex', [
            'technicians' => $technicians,
        ]);
    }

    public function store(StoreTechnicianRequest $request): RedirectResponse
    {
        $this->authorize('maintenance.technicians.manage');

        $user = $request->user();
        $hotelId = $this->activeHotelId($request);
        $data = $request->validated();

        Technician::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotelId,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'is_internal' => (bool) ($data['is_internal'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('ressources.technicians.index')
            ->with('success', 'Technicien enregistré.');
    }

    public function update(UpdateTechnicianRequest $request, Technician $technician): RedirectResponse
    {
        $this->authorize('maintenance.technicians.manage');

        $hotelId = $this->activeHotelId($request);

        abort_if($technician->tenant_id !== $request->user()->tenant_id, 404);
        abort_if((int) $technician->hotel_id !== (int) $hotelId, 404);

        $data = $request->validated();

        $technician->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'is_internal' => (bool) ($data['is_internal'] ?? false),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('ressources.technicians.index')
            ->with('success', 'Technicien mis à jour.');
    }
}
