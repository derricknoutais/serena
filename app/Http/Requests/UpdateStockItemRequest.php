<?php

namespace App\Http\Requests;

use App\Models\StockItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->user();
        $tenantId = $user?->tenant_id;
        $hotelId = (int) ($user?->active_hotel_id ?? $user?->hotel_id ?? 0);

        /** @var StockItem $item */
        $item = $this->route('stockItem');

        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('stock_items', 'name')
                ->ignore($item?->id ?? 0)
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('hotel_id', $hotelId))],
            'sku' => ['nullable', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:24'],
            'item_category' => ['required', 'string', 'max:32'],
            'default_purchase_price' => ['nullable', 'numeric', 'gte:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reorder_point' => ['nullable', 'numeric', 'gte:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
