<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BadgeLoginController extends Controller
{
    public function store(BadgeLoginRequest $request): RedirectResponse
    {
        $tenantId = tenant()?->getTenantKey();

        if (! $tenantId) {
            abort(404);
        }

        $badgeCode = Str::upper(trim((string) $request->input('badge_code')));

        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->where('badge_code', $badgeCode)
            ->first();

        if (! $user || ! $user->badge_pin) {
            throw ValidationException::withMessages([
                'badge_code' => 'Badge introuvable.',
            ]);
        }

        if (! Hash::check((string) $request->input('pin'), (string) $user->badge_pin)) {
            throw ValidationException::withMessages([
                'pin' => 'PIN invalide.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
