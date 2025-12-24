<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment_methods.create') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'code' => ['required', 'string'],
            'type' => ['nullable', 'string'],
            'provider' => ['nullable', 'string'],
            'account_number' => ['nullable', 'string'],
            'config' => ['nullable', 'json'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
