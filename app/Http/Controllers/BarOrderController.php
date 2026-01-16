<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Config\Concerns\ResolvesActiveHotel;
use App\Http\Requests\MoveBarOrderTableRequest;
use App\Http\Requests\OpenBarOrderForTableRequest;
use App\Models\BarOrder;
use App\Models\BarTable;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BarOrderController extends Controller
{
    use ResolvesActiveHotel;

    public function openForTable(OpenBarOrderForTableRequest $request): JsonResponse
    {
        $this->authorize('pos.create');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->requireActiveHotel($request);
        $data = $request->validated();

        $table = BarTable::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->findOrFail($data['bar_table_id']);

        $existing = BarOrder::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->where('bar_table_id', $table->id)
            ->whereIn('status', [BarOrder::STATUS_DRAFT, BarOrder::STATUS_OPEN])
            ->latest()
            ->first();

        if ($existing) {
            return response()->json([
                'order' => $this->orderPayload($existing->load('barTable')),
            ]);
        }

        $order = DB::transaction(function () use ($user, $hotel, $table): BarOrder {
            return BarOrder::query()->create([
                'tenant_id' => $user->tenant_id,
                'hotel_id' => $hotel->id,
                'bar_table_id' => $table->id,
                'status' => BarOrder::STATUS_OPEN,
                'opened_at' => now(),
                'cashier_user_id' => $user->id,
            ]);
        });

        return response()->json([
            'order' => $this->orderPayload($order->load('barTable')),
        ], 201);
    }

    public function moveTable(
        MoveBarOrderTableRequest $request,
        BarOrder $barOrder,
    ): JsonResponse {
        $this->authorize('pos.tables.manage');

        /** @var User $user */
        $user = $request->user();
        $hotel = $this->requireActiveHotel($request);
        $data = $request->validated();

        if ($barOrder->tenant_id !== $user->tenant_id || $barOrder->hotel_id !== $hotel->id) {
            abort(404);
        }

        if (! in_array($barOrder->status, [BarOrder::STATUS_DRAFT, BarOrder::STATUS_OPEN], true)) {
            throw ValidationException::withMessages([
                'bar_order_id' => 'Seules les commandes ouvertes peuvent changer de table.',
            ]);
        }

        $targetTable = BarTable::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->findOrFail($data['bar_table_id']);

        if ((int) $targetTable->id === (int) $barOrder->bar_table_id) {
            return response()->json([
                'order' => $this->orderPayload($barOrder->load('barTable')),
            ]);
        }

        $conflict = BarOrder::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hotel_id', $hotel->id)
            ->where('bar_table_id', $targetTable->id)
            ->whereIn('status', [BarOrder::STATUS_DRAFT, BarOrder::STATUS_OPEN])
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'bar_table_id' => 'Cette table a déjà une commande ouverte.',
            ]);
        }

        $barOrder->update([
            'bar_table_id' => $targetTable->id,
        ]);

        return response()->json([
            'order' => $this->orderPayload($barOrder->load('barTable')),
        ]);
    }

    private function orderPayload(BarOrder $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status,
            'opened_at' => $order->opened_at?->toDateTimeString(),
            'closed_at' => $order->closed_at?->toDateTimeString(),
            'bar_table' => $order->barTable?->only(['id', 'name', 'area']),
        ];
    }

    private function requireActiveHotel(Request $request): Hotel
    {
        $hotel = $this->activeHotel($request);

        abort_if($hotel === null, 403, 'Veuillez sélectionner un hôtel actif.');

        return $hotel;
    }
}
