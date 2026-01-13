<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.types.manage') ?? false;
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
        $typeId = $this->route('maintenanceType')?->id ?? $this->route('maintenanceType');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('maintenance_types', 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId)
                    ->ignore($typeId),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
