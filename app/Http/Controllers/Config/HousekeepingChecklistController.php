<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHousekeepingChecklistRequest;
use App\Http\Requests\UpdateHousekeepingChecklistRequest;
use App\Models\HousekeepingChecklist;
use App\Models\HousekeepingChecklistItem;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HousekeepingChecklistController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HousekeepingChecklist::class);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $activeHotelId = $this->activeHotelId($request);

        $checklists = HousekeepingChecklist::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($activeHotelId, function ($query) use ($activeHotelId): void {
                $query->where(function ($scoped) use ($activeHotelId): void {
                    $scoped->where('hotel_id', $activeHotelId)->orWhereNull('hotel_id');
                });
            })
            ->with(['roomType:id,name', 'items'])
            ->withCount('items')
            ->orderBy('scope')
            ->orderBy('name')
            ->get()
            ->map(fn (HousekeepingChecklist $checklist): array => [
                'id' => $checklist->id,
                'name' => $checklist->name,
                'scope' => $checklist->scope,
                'room_type_id' => $checklist->room_type_id,
                'room_type' => $checklist->roomType?->only(['id', 'name']),
                'is_active' => $checklist->is_active,
                'items_count' => $checklist->items_count,
                'items' => $checklist->items->map(fn (HousekeepingChecklistItem $item): array => [
                    'id' => $item->id,
                    'label' => $item->label,
                    'sort_order' => $item->sort_order,
                    'is_required' => $item->is_required,
                    'is_active' => $item->is_active,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $roomTypes = RoomType::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($activeHotelId, fn ($query) => $query->where('hotel_id', $activeHotelId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (RoomType $roomType): array => [
                'id' => $roomType->id,
                'name' => $roomType->name,
            ])
            ->values()
            ->all();

        return Inertia::render('Config/Housekeeping/HousekeepingChecklistsIndex', [
            'checklists' => $checklists,
            'roomTypes' => $roomTypes,
            'canManage' => $user->hasRole(['owner', 'manager', 'superadmin']),
        ]);
    }

    public function store(StoreHousekeepingChecklistRequest $request): RedirectResponse
    {
        $this->authorize('create', HousekeepingChecklist::class);

        $hotel = $this->activeHotel($request);

        if (! $hotel) {
            abort(404, 'Aucun hôtel actif sélectionné.');
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $payload = $this->normalizeChecklistPayload($request->validated());

        DB::transaction(function () use ($payload, $hotel, $user): void {
            $checklist = HousekeepingChecklist::query()->create([
                ...$payload,
                'tenant_id' => $user->tenant_id,
                'hotel_id' => $hotel->id,
            ]);

            if ($checklist->is_active) {
                $this->deactivateConflictingChecklists($checklist);
            }
        });

        return redirect()
            ->route('ressources.housekeeping-checklists.index')
            ->with('success', 'Checklist créée.');
    }

    public function update(
        UpdateHousekeepingChecklistRequest $request,
        HousekeepingChecklist $housekeepingChecklist
    ): RedirectResponse {
        $checklist = $this->resolveChecklist($request, $housekeepingChecklist);

        $this->authorize('update', $checklist);

        $payload = $this->normalizeChecklistPayload($request->validated());

        DB::transaction(function () use ($checklist, $payload): void {
            $checklist->update($payload);

            if ($checklist->is_active) {
                $this->deactivateConflictingChecklists($checklist);
            }
        });

        return redirect()
            ->route('ressources.housekeeping-checklists.index')
            ->with('success', 'Checklist mise à jour.');
    }

    public function destroy(Request $request, HousekeepingChecklist $housekeepingChecklist): RedirectResponse
    {
        $checklist = $this->resolveChecklist($request, $housekeepingChecklist);

        $this->authorize('delete', $checklist);

        $checklist->delete();

        return redirect()
            ->route('ressources.housekeeping-checklists.index')
            ->with('success', 'Checklist supprimée.');
    }

    public function duplicate(Request $request, HousekeepingChecklist $housekeepingChecklist): RedirectResponse
    {
        $this->authorize('create', HousekeepingChecklist::class);

        $checklist = $this->resolveChecklist($request, $housekeepingChecklist);

        DB::transaction(function () use ($checklist): void {
            $copy = $checklist->replicate();
            $copy->name = sprintf('%s (copie)', $checklist->name);
            $copy->is_active = false;
            $copy->save();

            $checklist->items()
                ->orderBy('sort_order')
                ->get()
                ->each(function (HousekeepingChecklistItem $item) use ($copy): void {
                    $copy->items()->create([
                        'label' => $item->label,
                        'sort_order' => $item->sort_order,
                        'is_required' => $item->is_required,
                        'is_active' => $item->is_active,
                    ]);
                });
        });

        return redirect()
            ->route('ressources.housekeeping-checklists.index')
            ->with('success', 'Checklist dupliquée.');
    }

    private function resolveChecklist(Request $request, HousekeepingChecklist $checklist): HousekeepingChecklist
    {
        $activeHotelId = $this->activeHotelId($request);

        return HousekeepingChecklist::query()
            ->whereKey($checklist->getKey())
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($activeHotelId, function ($query) use ($activeHotelId): void {
                $query->where(function ($scoped) use ($activeHotelId): void {
                    $scoped->where('hotel_id', $activeHotelId)->orWhereNull('hotel_id');
                });
            })
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeChecklistPayload(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        if (($data['scope'] ?? null) === HousekeepingChecklist::SCOPE_GLOBAL) {
            $data['room_type_id'] = null;
        }

        return $data;
    }

    private function deactivateConflictingChecklists(HousekeepingChecklist $checklist): void
    {
        $query = HousekeepingChecklist::query()
            ->where('tenant_id', $checklist->tenant_id)
            ->where('scope', $checklist->scope)
            ->whereKeyNot($checklist->getKey());

        if ($checklist->hotel_id !== null) {
            $query->where('hotel_id', $checklist->hotel_id);
        }

        if ($checklist->scope === HousekeepingChecklist::SCOPE_ROOM_TYPE) {
            $query->where('room_type_id', $checklist->room_type_id);
        }

        $query->update([
            'is_active' => false,
        ]);
    }
}
