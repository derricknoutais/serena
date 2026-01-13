<?php

namespace App\Http\Requests;

use App\Models\MaintenanceTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MaintenanceTicket|null $ticket */
        $ticket = $this->route('maintenanceTicket');

        return $ticket !== null && ($this->user()?->can('update', $ticket) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $tenantId = $user?->tenant_id;

        return [
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    MaintenanceTicket::STATUS_OPEN,
                    MaintenanceTicket::STATUS_IN_PROGRESS,
                    MaintenanceTicket::STATUS_RESOLVED,
                    MaintenanceTicket::STATUS_CLOSED,
                ]),
            ],
            'severity' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    MaintenanceTicket::SEVERITY_LOW,
                    MaintenanceTicket::SEVERITY_MEDIUM,
                    MaintenanceTicket::SEVERITY_HIGH,
                    MaintenanceTicket::SEVERITY_CRITICAL,
                ]),
            ],
            'assigned_to_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'maintenance_type_id' => [
                'nullable',
                'integer',
                Rule::exists('maintenance_types', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $user?->active_hotel_id ?? $user?->hotel_id ?? 0),
            ],
            'description' => ['nullable', 'string'],
            'blocks_sale' => ['nullable', 'boolean'],
            'restore_room_status' => ['sometimes', 'boolean'],
        ];
    }
}
