<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\Hotel;
use App\Models\User;
use App\Support\PermissionsCatalog;
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
        PermissionsCatalog::ensureExists();

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
            ->with(['roles', 'hotels:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'is_owner' => $user->hasRole('owner'),
                'active_hotel_id' => $user->active_hotel_id,
                'hotels' => $user->hotels->map(fn ($hotel) => [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                ])->values(),
            ]);

        $tenantId = tenant()?->getTenantKey() ?? auth()->user()?->tenant_id;

        $hotels = Hotel::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name']);

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
            'hotels' => $hotels,
            'permissionGroups' => $permissions,
        ]);
    }

    public function update(Role $role, UpdateRolePermissionsRequest $request): RedirectResponse
    {
        PermissionsCatalog::ensureExists();

        $role->syncPermissions($request->validated('permissions', []));

        return back()->with('success', 'Permissions mises à jour.');
    }
}
