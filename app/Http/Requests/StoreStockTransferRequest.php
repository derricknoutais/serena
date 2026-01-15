<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
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
            'from_location_id' => [
                'required',
                Rule::exists('storage_locations', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('hotel_id', $hotelId)),
            ],
            'to_location_id' => [
                'required',
                Rule::exists('storage_locations', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('hotel_id', $hotelId)),
            ],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => [
                'required',
                Rule::exists('stock_items', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('hotel_id', $hotelId)),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
