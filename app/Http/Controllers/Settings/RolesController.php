<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->orderBy('name')
            ->with('permissions:id,name')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
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

        $permissions = Permission::query()
            ->orderBy('name')
            ->get()
            ->map(function (Permission $permission): array {
                $parts = explode('.', $permission->name);
                $groupKey = $parts[0] ?? $permission->name;
                $actionKey = $parts[1] ?? '';

                return [
                    'name' => $permission->name,
                    'group' => Str::headline(str_replace('_', ' ', $groupKey)),
                    'label' => $actionKey ? Str::headline(str_replace('_', ' ', $actionKey)) : Str::headline($permission->name),
                ];
            })
            ->groupBy('group')
            ->map(fn ($items, $group) => [
                'group' => $group,
                'items' => $items->values(),
            ])
            ->values();

        return Inertia::render('settings/Roles', [
            'roles' => $roles,
            'users' => $users,
            'permissionGroups' => $permissions,
        ]);
    }

    public function update(Role $role, UpdateRolePermissionsRequest $request): RedirectResponse
    {
        $role->syncPermissions($request->validated('permissions', []));

        return back()->with('success', 'Permissions mises à jour.');
    }
}
