<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($role) => [
                'name' => $role->name,
            ]);

        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'is_owner' => $user->hasRole('owner'),
            ]);

        return Inertia::render('settings/Roles', [
            'roles' => $roles,
            'users' => $users,
        ]);
    }
}
