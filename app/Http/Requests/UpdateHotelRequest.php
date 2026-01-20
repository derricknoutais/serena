<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hotelId = $this->user()?->active_hotel_id ?? $this->user()?->hotel_id;

        return [
            'name' => ['required', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['string', 'nullable'],
            'check_in_time' => ['required'],
            'check_out_time' => ['required'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
            'early_policy' => ['nullable', 'string', Rule::in(['forbidden', 'free', 'paid'])],
            'early_fee_type' => ['nullable', 'string', Rule::in(['flat', 'percent'])],
            'early_fee_value' => ['nullable', 'numeric', 'min:0'],
            'early_cutoff_time' => ['nullable', 'string'],
            'late_policy' => ['nullable', 'string', Rule::in(['forbidden', 'free', 'paid'])],
            'late_fee_type' => ['nullable', 'string', Rule::in(['flat', 'percent'])],
            'late_fee_value' => ['nullable', 'numeric', 'min:0'],
            'late_max_time' => ['nullable', 'string'],
            'document_display_name' => ['nullable', 'string', 'max:160'],
            'document_contact_address' => ['nullable', 'string', 'max:255'],
            'document_contact_phone' => ['nullable', 'string', 'max:50'],
            'document_contact_email' => ['nullable', 'email', 'max:255'],
            'document_legal_nif' => ['nullable', 'string', 'max:120'],
            'document_legal_rccm' => ['nullable', 'string', 'max:120'],
            'document_header_text' => ['nullable', 'string'],
            'document_footer_text' => ['nullable', 'string'],
            'default_bar_stock_location_id' => [
                'nullable',
                'integer',
                Rule::exists('storage_locations', 'id')
                    ->where('tenant_id', $this->user()?->tenant_id)
                    ->where('hotel_id', $hotelId),
            ],
        ];
    }
}
