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
        $role = $request->string('role')->toString();

        if ($user->hasRole('owner') || $role === 'owner') {
            return back()->withErrors(['role' => 'Le rôle Owner ne peut pas être modifié.']);
        }

        $user->syncRoles([$role]);

        activity('users')
            ->event('role_updated')
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties([
                'role' => $role,
            ])
            ->log('Rôle mis à jour');

        return back()->with('status', 'Rôle mis à jour.');
    }
}
