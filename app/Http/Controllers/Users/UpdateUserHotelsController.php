<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserHotelsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateUserHotelsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateUserHotelsRequest $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        if ($currentUser && (string) $currentUser->tenant_id !== (string) $user->tenant_id) {
            abort(403);
        }

        $hotelIds = $request->validated('hotel_ids', []);

        if (! is_array($hotelIds)) {
            $hotelIds = [];
        }

        $hotelIds = array_values(array_unique(array_map('intval', $hotelIds)));
        $user->hotels()->sync($hotelIds);

        if ($user->active_hotel_id === null && $hotelIds !== []) {
            $user->forceFill(['active_hotel_id' => $hotelIds[0]])->save();
        }

        activity('users')
            ->event('hotel_assigned')
            ->causedBy($currentUser)
            ->performedOn($user)
            ->withProperties([
                'hotel_ids' => $hotelIds,
            ])
            ->log('Hôtels attribués');

        return back()->with('status', 'Hôtels attribués.');
    }
}
