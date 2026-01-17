<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeReservationRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reservation = $this->route('reservation');
        $requiresUsage = $reservation?->room_id !== null;

        return [
            'room_id' => ['required', 'uuid', 'exists:rooms,id'],
            'vacated_usage' => [
                $requiresUsage ? 'required' : 'nullable',
                'string',
                Rule::in(['not_used', 'used', 'unknown']),
            ],
            'moved_at' => ['nullable', 'date'],
        ];
    }
}
