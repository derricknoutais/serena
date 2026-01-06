<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinishInspectionRequest extends FormRequest
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
        return [
            'items' => ['sometimes', 'array'],
            'items.*.checklist_item_id' => ['required', 'integer', 'exists:housekeeping_checklist_items,id'],
            'items.*.is_ok' => ['required', 'boolean'],
            'items.*.note' => ['nullable', 'string', 'max:255', 'required_if:items.*.is_ok,false'],
        ];
    }
}
