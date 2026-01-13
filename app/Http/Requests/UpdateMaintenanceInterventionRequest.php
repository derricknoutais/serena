<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceInterventionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.interventions.update') ?? false;
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
            'technician_id' => [
                'nullable',
                'integer',
                Rule::exists('technicians', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId),
            ],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'summary' => ['nullable', 'string'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'parts_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tickets' => ['nullable', 'array'],
            'tickets.*.maintenance_ticket_id' => [
                'required',
                'integer',
                Rule::exists('maintenance_tickets', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId),
            ],
            'tickets.*.work_done' => ['nullable', 'string'],
            'tickets.*.labor_cost' => ['nullable', 'numeric', 'min:0'],
            'tickets.*.parts_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
