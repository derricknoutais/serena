<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserConfigController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles')
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
            ]);

        return Inertia::render('Config/Users/UsersIndex', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Config/Users/UsersCreate', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
        ]);

        $user = User::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        if (! empty($data['role'])) {
            $user->assignRole($data['role']);
        }

        return redirect()->route('ressources.users.index')->with('success', 'Utilisateur créé.');
    }

    public function edit(Request $request, int $id): Response
    {
        $user = User::query()
            ->with('roles')
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $roles = Role::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Config/Users/UsersEdit', [
            'userItem' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email,'.$id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
        ]);

        $user = User::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }

        $user->save();

        if (! empty($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return redirect()->route('ressources.users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = User::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $user->delete();

        return redirect()->route('ressources.users.index')->with('success', 'Utilisateur supprimé.');
    }
}
