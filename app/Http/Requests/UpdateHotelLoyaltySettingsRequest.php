<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHotelLoyaltySettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('loyalty.settings.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $enabled = $this->boolean('enabled');
        $mode = $this->input('earning_mode');

        return [
            'enabled' => ['required', 'boolean'],
            'earning_mode' => ['required', 'string', Rule::in(['amount', 'nights', 'fixed'])],
            'points_per_amount' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf($enabled && $mode === 'amount'),
            ],
            'amount_base' => [
                'nullable',
                'numeric',
                'min:0.01',
                Rule::requiredIf($enabled && $mode === 'amount'),
            ],
            'points_per_night' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf($enabled && $mode === 'nights'),
            ],
            'fixed_points' => [
                'nullable',
                'integer',
                'min:1',
                Rule::requiredIf($enabled && $mode === 'fixed'),
            ],
            'max_points_per_stay' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enabled.required' => 'Veuillez indiquer si le programme est activé.',
            'earning_mode.required' => 'Veuillez sélectionner un mode de gain.',
            'earning_mode.in' => 'Le mode de gain sélectionné est invalide.',
            'points_per_amount.required' => 'Le nombre de points par montant est requis.',
            'points_per_amount.min' => 'Le nombre de points par montant doit être supérieur à 0.',
            'amount_base.required' => 'La base de montant est requise.',
            'amount_base.min' => 'La base de montant doit être supérieure à 0.',
            'points_per_night.required' => 'Le nombre de points par nuit est requis.',
            'points_per_night.min' => 'Le nombre de points par nuit doit être supérieur à 0.',
            'fixed_points.required' => 'Le nombre de points fixes est requis.',
            'fixed_points.min' => 'Le nombre de points fixes doit être supérieur à 0.',
            'max_points_per_stay.min' => 'Le plafond de points doit être supérieur à 0.',
        ];
    }
}
