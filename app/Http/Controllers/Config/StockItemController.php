<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockItemRequest;
use App\Http\Requests\UpdateStockItemRequest;
use App\Models\StockItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockItemController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): Response
    {
        $this->authorize('stock.items.manage');

        $hotelId = $this->activeHotelId($request);

        $stockItems = StockItem::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($hotelId, fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderBy('name')
            ->get()
            ->map(fn (StockItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'unit' => $item->unit,
                'category' => $item->item_category,
                'default_purchase_price' => $item->default_purchase_price,
                'currency' => $item->currency,
                'reorder_point' => $item->reorder_point,
                'is_active' => (bool) $item->is_active,
            ]);

        return Inertia::render('Config/StockItems/StockItemsIndex', [
            'stockItems' => $stockItems,
        ]);
    }

    public function store(StoreStockItemRequest $request): RedirectResponse
    {
        $this->authorize('stock.items.manage');

        $user = $request->user();
        $hotelId = $this->activeHotelId($request);
        $data = $request->validated();

        StockItem::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotelId,
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'unit' => $data['unit'],
            'item_category' => $data['item_category'],
            'default_purchase_price' => $data['default_purchase_price'] ?? 0,
            'currency' => $data['currency'] ?? 'XAF',
            'reorder_point' => $data['reorder_point'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('ressources.stock-items.index')->with('success', 'Article enregistré.');
    }

    public function update(UpdateStockItemRequest $request, StockItem $stockItem): RedirectResponse
    {
        $this->authorize('stock.items.manage');

        $hotelId = $this->activeHotelId($request);

        abort_if($stockItem->tenant_id !== $request->user()->tenant_id, 404);
        abort_if((int) $stockItem->hotel_id !== (int) $hotelId, 404);

        $data = $request->validated();

        $stockItem->update([
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'unit' => $data['unit'],
            'item_category' => $data['item_category'],
            'default_purchase_price' => $data['default_purchase_price'] ?? 0,
            'currency' => $data['currency'] ?? $stockItem->currency,
            'reorder_point' => $data['reorder_point'] ?? $stockItem->reorder_point,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('ressources.stock-items.index')->with('success', 'Article mis à jour.');
    }
}
