<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\SwitchUserBadgeRequest;
use App\Http\Requests\SwitchUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SwitchUserController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        $users = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->orderBy('name')
            ->with('roles')
            ->get()
            ->map(static fn (User $tenantUser) => [
                'id' => $tenantUser->id,
                'name' => $tenantUser->name,
                'email' => $tenantUser->email,
                'avatar' => $tenantUser->avatar ?? null,
                'role' => $tenantUser->roles->first()?->name,
            ]);

        return Inertia::render('auth/SwitchUser', [
            'users' => $users,
            'currentUserId' => $user->id,
        ]);
    }

    public function store(SwitchUserRequest $request): RedirectResponse
    {
        $currentUser = $request->user();

        abort_unless($currentUser, 403);

        $targetUser = User::query()
            ->where('tenant_id', $currentUser->tenant_id)
            ->findOrFail((int) $request->input('user_id'));

        $password = (string) $request->input('password');

        if (! Hash::check($password, (string) $targetUser->password)) {
            throw ValidationException::withMessages([
                'password' => 'Mot de passe invalide.',
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::login($targetUser);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Utilisateur changé.');
    }

    public function storeBadge(SwitchUserBadgeRequest $request): RedirectResponse
    {
        $currentUser = $request->user();

        abort_unless($currentUser, 403);

        $badgeCode = Str::upper(trim((string) $request->input('badge_code')));

        $targetUser = User::query()
            ->where('tenant_id', $currentUser->tenant_id)
            ->where('badge_code', $badgeCode)
            ->first();

        if (! $targetUser || ! $targetUser->badge_pin) {
            throw ValidationException::withMessages([
                'badge_code' => 'Badge introuvable.',
            ]);
        }

        if (! Hash::check((string) $request->input('pin'), (string) $targetUser->badge_pin)) {
            throw ValidationException::withMessages([
                'pin' => 'PIN invalide.',
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::login($targetUser);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Utilisateur changé.');
    }
}
