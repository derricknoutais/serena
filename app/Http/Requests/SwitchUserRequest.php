<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchUserRequest extends FormRequest
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
        $tenantId = $this->user()?->tenant_id;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Veuillez sélectionner un compte.',
            'user_id.exists' => "Le compte sélectionné n'est pas disponible.",
            'password.required' => 'Le mot de passe est requis.',
        ];
    }
}
