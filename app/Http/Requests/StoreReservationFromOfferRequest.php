<?php

namespace App\Http\Requests;

use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationFromOfferRequest extends FormRequest
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
        $user = $this->user();
        $tenantId = (string) ($user?->tenant_id ?? '');
        $hotelId = (int) ($user?->active_hotel_id ?? $user?->hotel_id ?? 0);

        return [
            'offer_id' => [
                'required',
                'integer',
                Rule::exists('offers', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId),
            ],
            'room_id' => [
                'required',
                'uuid',
                Rule::exists('rooms', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId),
            ],
            'guest_id' => ['nullable', 'integer', Rule::exists('guests', 'id')->where('tenant_id', $tenantId)],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in([
                Reservation::STATUS_PENDING,
                Reservation::STATUS_CONFIRMED,
            ])],
            'code' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'base_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            $this->merge([
                'status' => Reservation::STATUS_PENDING,
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Le statut doit être "en attente" ou "confirmée" à la création.',
        ];
    }
}
