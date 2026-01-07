<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BadgePinRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'regex:/^\\d{4,8}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.required' => 'Le PIN est requis.',
            'pin.regex' => 'Le PIN doit contenir entre 4 et 8 chiffres.',
        ];
    }
}
