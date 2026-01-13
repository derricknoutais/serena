<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachMaintenanceInterventionTicketRequest extends FormRequest
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
            'maintenance_ticket_id' => [
                'required',
                'integer',
                Rule::exists('maintenance_tickets', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId),
            ],
            'work_done' => ['nullable', 'string'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'parts_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
