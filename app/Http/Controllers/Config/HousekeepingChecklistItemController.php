<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderHousekeepingChecklistItemsRequest;
use App\Http\Requests\StoreHousekeepingChecklistItemRequest;
use App\Http\Requests\UpdateHousekeepingChecklistItemRequest;
use App\Models\HousekeepingChecklist;
use App\Models\HousekeepingChecklistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HousekeepingChecklistItemController extends Controller
{
    use ResolvesActiveHotel;

    public function store(
        StoreHousekeepingChecklistItemRequest $request,
        HousekeepingChecklist $housekeepingChecklist
    ): RedirectResponse {
        $checklist = $this->resolveChecklist($request, $housekeepingChecklist);

        $this->authorize('update', $checklist);

        $data = $request->validated();
        $nextOrder = (int) ($checklist->items()->max('sort_order') ?? 0) + 1;

        HousekeepingChecklistItem::query()->create([
            ...$data,
            'checklist_id' => $checklist->id,
            'sort_order' => $nextOrder,
        ]);

        return redirect()
            ->route('ressources.housekeeping-checklists.index')
            ->with('success', 'Item ajouté.');
    }

    public function update(
        UpdateHousekeepingChecklistItemRequest $request,
        HousekeepingChecklist $housekeepingChecklist,
        HousekeepingChecklistItem $item
    ): RedirectResponse {
        $checklist = $this->resolveChecklist($request, $housekeepingChecklist);

        $this->authorize('update', $checklist);

        $item = $this->resolveItem($checklist, $item);
        $item->update($request->validated());

        return redirect()
            ->route('ressources.housekeeping-checklists.index')
            ->with('success', 'Item mis à jour.');
    }

    public function destroy(
        Request $request,
        HousekeepingChecklist $housekeepingChecklist,
        HousekeepingChecklistItem $item
    ): RedirectResponse {
        $checklist = $this->resolveChecklist($request, $housekeepingChecklist);

        $this->authorize('update', $checklist);

        $item = $this->resolveItem($checklist, $item);
        $item->delete();

        return redirect()
            ->route('ressources.housekeeping-checklists.index')
            ->with('success', 'Item supprimé.');
    }

    public function reorder(
        ReorderHousekeepingChecklistItemsRequest $request,
        HousekeepingChecklist $housekeepingChecklist
    ): RedirectResponse {
        $checklist = $this->resolveChecklist($request, $housekeepingChecklist);

        $this->authorize('update', $checklist);

        $payload = $request->validated()['items'];
        $ids = collect($payload)->pluck('id')->all();

        $items = HousekeepingChecklistItem::query()
            ->where('checklist_id', $checklist->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($items->count() !== count($payload)) {
            abort(422, 'Les items fournis ne correspondent pas à la checklist.');
        }

        DB::transaction(function () use ($items, $payload): void {
            foreach ($payload as $entry) {
                $item = $items->get($entry['id']);
                if (! $item) {
                    continue;
                }

                $item->update([
                    'sort_order' => $entry['sort_order'],
                ]);
            }
        });

        return redirect()
            ->route('ressources.housekeeping-checklists.index')
            ->with('success', 'Ordre mis à jour.');
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

    private function resolveItem(HousekeepingChecklist $checklist, HousekeepingChecklistItem $item): HousekeepingChecklistItem
    {
        return HousekeepingChecklistItem::query()
            ->whereKey($item->getKey())
            ->where('checklist_id', $checklist->id)
            ->firstOrFail();
    }
}
