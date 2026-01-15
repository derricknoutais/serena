<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceInterventionItemRequest extends FormRequest
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
        /**
         * @var \App\Models\User|null $user
         */
        $user = $this->user();
        $tenantId = $user?->tenant_id;
        $hotelId = (int) ($user?->active_hotel_id ?? $user?->hotel_id ?? 0);

        return [
            'stock_item_id' => [
                'required',
                Rule::exists('stock_items', 'id')->where(function ($query) use ($tenantId, $hotelId): void {
                    $query->where('tenant_id', $tenantId)
                        ->where('hotel_id', $hotelId);
                }),
            ],
            'storage_location_id' => [
                'required',
                Rule::exists('storage_locations', 'id')->where(function ($query) use ($tenantId, $hotelId): void {
                    $query->where('tenant_id', $tenantId)
                        ->where('hotel_id', $hotelId);
                }),
            ],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'allow_negative_stock' => ['sometimes', 'boolean'],
        ];
    }
}
