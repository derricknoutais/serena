<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $payment = $this->route('payment');

        return $payment
            ? ($this->user()?->can('refund', $payment) ?? false)
            : false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $payment = $this->route('payment');
        $tenantId = $payment?->tenant_id;
        $hotelId = $payment?->hotel_id;

        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where(function ($query) use ($hotelId): void {
                        $query->whereNull('hotel_id')->orWhere('hotel_id', $hotelId);
                    }),
            ],
            'cash_session_id' => [
                'nullable',
                'integer',
                Rule::exists('cash_sessions', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('hotel_id', $hotelId)
                    ->where('status', 'open'),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
            'refund_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
