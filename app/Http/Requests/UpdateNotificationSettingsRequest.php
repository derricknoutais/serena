<?php

namespace App\Http\Requests;

use App\Support\NotificationEventCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

class UpdateNotificationSettingsRequest extends FormRequest
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
        $eventKeys = collect(NotificationEventCatalog::all())
            ->pluck('key')
            ->all();

        $roleNames = Role::query()->pluck('name')->all();
        $channels = [
            NotificationEventCatalog::CHANNEL_IN_APP,
            NotificationEventCatalog::CHANNEL_PUSH,
        ];

        return [
            'events' => ['required', 'array'],
            'events.*.roles' => ['required', 'array'],
            'events.*.roles.*' => ['string', 'in:'.implode(',', $roleNames)],
            'events.*.channels' => ['required', 'array'],
            'events.*.channels.*' => ['string', 'in:'.implode(',', $channels)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $eventKeys = collect(NotificationEventCatalog::all())
                ->pluck('key')
                ->all();

            $events = $this->input('events', []);
            foreach (array_keys((array) $events) as $eventKey) {
                if (! in_array($eventKey, $eventKeys, true)) {
                    $validator->errors()->add('events', 'Évènement invalide.');
                    break;
                }
            }
        });
    }
}
