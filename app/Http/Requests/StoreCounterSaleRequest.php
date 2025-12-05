<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCounterSaleRequest extends FormRequest
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
        $tenantId = $this->user()?->tenant_id;
        $hotelId = $this->user()?->active_hotel_id ?? $this->session()->get('active_hotel_id');

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where(function ($query) use ($hotelId): void {
                        if ($hotelId !== null) {
                            $query->where('hotel_id', $hotelId);
                        }
                    }),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_amount' => ['required', 'numeric', 'min:0'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where(function ($query) use ($hotelId): void {
                        $query->whereNull('hotel_id');

                        if ($hotelId !== null) {
                            $query->orWhere('hotel_id', $hotelId);
                        }
                    }),
            ],
            'client_label' => ['nullable', 'string', 'max:255'],
        ];
    }
}
