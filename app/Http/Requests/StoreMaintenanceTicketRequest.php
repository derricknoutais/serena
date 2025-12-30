<?php

namespace App\Http\Requests;

use App\Models\MaintenanceTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MaintenanceTicket::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $tenantId = $user?->tenant_id;
        $hotelId = $user?->active_hotel_id ?? $user?->hotel_id ?? 0;

        return [
            'room_id' => [
                'required',
                'uuid',
                Rule::exists('rooms', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId),
            ],
            'title' => ['required', 'string', 'max:160'],
            'severity' => [
                'required',
                'string',
                Rule::in([
                    MaintenanceTicket::SEVERITY_LOW,
                    MaintenanceTicket::SEVERITY_MEDIUM,
                    MaintenanceTicket::SEVERITY_HIGH,
                    MaintenanceTicket::SEVERITY_CRITICAL,
                ]),
            ],
            'description' => ['nullable', 'string'],
            'blocks_sale' => ['nullable', 'boolean'],
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
