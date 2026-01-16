<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Requests\StoreBarTableRequest;
use App\Http\Requests\UpdateBarTableRequest;
use App\Models\BarOrder;
use App\Models\BarTable;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BarTableController extends Controller
{
    use ResolvesActiveHotel;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('pos.view');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->requireActiveHotel($request);

        $tables = BarTable::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $openOrders = BarOrder::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->whereIn('status', [BarOrder::STATUS_DRAFT, BarOrder::STATUS_OPEN])
            ->whereNotNull('bar_table_id')
            ->with('cashier')
            ->get()
            ->groupBy('bar_table_id')
            ->map(fn (Collection $orders) => $orders->first());

        $payload = $tables->map(function (BarTable $table) use ($openOrders) {
            $openOrder = $openOrders->get($table->id);

            return [
                'id' => $table->id,
                'name' => $table->name,
                'area' => $table->area,
                'capacity' => $table->capacity,
                'sort_order' => $table->sort_order,
                'is_active' => $table->is_active,
                'open_order' => $openOrder
                    ? [
                        'id' => $openOrder->id,
                        'status' => $openOrder->status,
                        'opened_at' => $openOrder->opened_at?->toDateTimeString(),
                        'cashier' => $openOrder->cashier?->only(['id', 'name']),
                    ]
                    : null,
            ];
        });

        return response()->json([
            'tables' => $payload,
        ]);
    }

    public function store(StoreBarTableRequest $request): JsonResponse
    {
        $this->authorize('pos.tables.manage');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->requireActiveHotel($request);
        $data = $request->validated();

        $table = BarTable::query()->create([
            'tenant_id' => $user->tenant_id,
            'hotel_id' => $hotel->id,
            'name' => $data['name'],
            'area' => $data['area'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json([
            'table' => $table,
        ], 201);
    }

    public function update(UpdateBarTableRequest $request, BarTable $barTable): JsonResponse
    {
        $this->authorize('pos.tables.manage');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->requireActiveHotel($request);

        if ($barTable->tenant_id !== $user->tenant_id || $barTable->hotel_id !== $hotel->id) {
            abort(404);
        }

        $data = $request->validated();

        $barTable->update([
            'name' => $data['name'],
            'area' => $data['area'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json([
            'table' => $barTable,
        ]);
    }

    private function requireActiveHotel(Request $request): Hotel
    {
        $hotel = $this->activeHotel($request);

        abort_if($hotel === null, 403, 'Veuillez sélectionner un hôtel actif.');

        return $hotel;
    }
}
