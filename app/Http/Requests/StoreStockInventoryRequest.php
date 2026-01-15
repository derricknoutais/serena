<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $tenantId = $user?->tenant_id;
        $hotelId = (int) ($user?->active_hotel_id ?? $user?->hotel_id ?? 0);

        return [
            'storage_location_id' => [
                'required',
                Rule::exists('storage_locations', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('hotel_id', $hotelId)),
            ],
            'counted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => [
                'required',
                Rule::exists('stock_items', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('hotel_id', $hotelId)),
            ],
            'lines.*.counted_quantity' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
