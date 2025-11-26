<?php

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function show(Request $request): Response
    {
        $invitation = $this->findActiveInvitation(
            $request->query('token'),
            $request->query('email'),
        );

        if ($invitation === null) {
            abort(404);
        }

        return Inertia::render('invitations/Accept', [
            'email' => $invitation->email,
            'token' => $request->query('token'),
            'tenant' => [
                'name' => tenant()?->name ?? tenant()?->getTenantKey(),
            ],
        ]);
    }

    public function store(AcceptInvitationRequest $request): RedirectResponse
    {
        $token = $request->string('token')->toString();
        $email = $request->string('email')->toString();

        $invitation = $this->findActiveInvitation($token, $email);

        if ($invitation === null) {
            abort(404);
        }

        $user = User::query()->firstOrCreate(
            [
                'tenant_id' => $invitation->tenant_id,
                'email' => $invitation->email,
            ],
            [
                'name' => $request->string('name')->toString(),
                'password' => Hash::make($request->string('password')->toString()),
                'email_verified_at' => now(),
            ],
        );

        $invitation->forceFill([
            'accepted_at' => now(),
            'token' => hash('sha256', Str::random(64)),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function findActiveInvitation(?string $token, ?string $email): ?Invitation
    {
        if ($token === null || $token === '' || $email === null || $email === '') {
            return null;
        }

        $hashedToken = hash('sha256', $token);

        return Invitation::query()
            ->where('token', $hashedToken)
            ->where('email', $email)
            ->where('tenant_id', tenant()?->getTenantKey())
            ->whereNull('accepted_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }
}
