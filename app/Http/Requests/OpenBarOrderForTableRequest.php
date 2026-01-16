<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenBarOrderForTableRequest extends FormRequest
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
        /** @var \App\Models\User|null $user */
        $user = $this->user();
        $tenantId = $user?->tenant_id;
        $hotelId = (int) ($user?->active_hotel_id ?? $user?->hotel_id ?? 0);

        return [
            'bar_table_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('bar_tables', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)
                        ->where('hotel_id', $hotelId)
                        ->where('is_active', true)),
            ],
        ];
    }
}
