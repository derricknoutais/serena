<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $tenantId = $user?->tenant_id;
        $hotelId = (int) ($user?->active_hotel_id ?? $user?->hotel_id ?? 0);

        return [
            'storage_location_id' => [
                'required',
                Rule::exists('storage_locations', 'id')->where(function ($query) use ($tenantId, $hotelId): void {
                    $query->where('tenant_id', $tenantId)->where('hotel_id', $hotelId);
                }),
            ],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'purchased_at' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => [
                'required',
                Rule::exists('stock_items', 'id')->where(function ($query) use ($tenantId, $hotelId): void {
                    $query->where('tenant_id', $tenantId)->where('hotel_id', $hotelId);
                }),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'gte:0'],
            'lines.*.currency' => ['nullable', 'string', 'size:3'],
            'lines.*.notes' => ['nullable', 'string'],
        ];
    }
}
