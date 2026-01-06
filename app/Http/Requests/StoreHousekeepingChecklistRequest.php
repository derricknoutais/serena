<?php

namespace App\Http\Requests;

use App\Models\HousekeepingChecklist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHousekeepingChecklistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $tenantId = $user?->tenant_id;
        $hotelId = $user?->active_hotel_id ?? $user?->hotel_id ?? 0;

        return [
            'name' => ['required', 'string', 'max:160'],
            'scope' => ['required', 'string', Rule::in([
                HousekeepingChecklist::SCOPE_GLOBAL,
                HousekeepingChecklist::SCOPE_ROOM_TYPE,
            ])],
            'room_type_id' => [
                'nullable',
                'integer',
                'required_if:scope,'.HousekeepingChecklist::SCOPE_ROOM_TYPE,
                'prohibited_if:scope,'.HousekeepingChecklist::SCOPE_GLOBAL,
                Rule::exists('room_types', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
