<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStorageLocationRequest extends FormRequest
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

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('storage_locations', 'name')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('hotel_id', $hotelId))],
            'code' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', 'string', 'max:32'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
