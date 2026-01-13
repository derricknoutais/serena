<?php

namespace App\Http\Requests;

use App\Models\MaintenanceTicket;
use Illuminate\Foundation\Http\FormRequest;

class CloseMaintenanceTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var MaintenanceTicket|null $ticket */
        $ticket = $this->route('maintenanceTicket');

        return $ticket !== null && ($this->user()?->can('close', $ticket) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'closed_at' => ['nullable', 'date'],
            'restore_room_status' => ['sometimes', 'boolean'],
        ];
    }
}
