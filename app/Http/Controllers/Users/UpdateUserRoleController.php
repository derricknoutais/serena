<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateUserRoleController extends Controller
{
    public function __invoke(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        if ($currentUser && (string) $currentUser->tenant_id !== (string) $user->tenant_id) {
            abort(403);
        }

        $role = $request->string('role')->toString();

        if ($user->hasRole('owner') || $role === 'owner') {
            return back()->withErrors(['role' => 'Le rôle Owner ne peut pas être modifié.']);
        }

        $user->syncRoles([$role]);

        $hotelIds = $request->validated('hotel_ids', []);
        if (is_array($hotelIds)) {
            $hotelIds = array_values(array_unique(array_map('intval', $hotelIds)));
            $user->hotels()->sync($hotelIds);

            if ($user->active_hotel_id === null && $hotelIds !== []) {
                $user->forceFill(['active_hotel_id' => $hotelIds[0]])->save();
            }
        }

        activity('users')
            ->event('role_updated')
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties([
                'role' => $role,
                'hotel_ids' => $hotelIds ?? [],
            ])
            ->log('Rôle et hôtels mis à jour');

        return back()->with('status', 'Rôle et hôtels mis à jour.');
    }
}
