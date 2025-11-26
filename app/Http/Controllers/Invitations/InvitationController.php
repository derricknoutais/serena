<?php

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\Invitation;
use App\Notifications\InvitationCreated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request): RedirectResponse
    {
        $tenantId = tenant()?->getTenantKey() ?? $request->user()?->tenant_id;
        if ($tenantId === null) {
            abort(404);
        }
        $token = Str::random(64);
        $hashedToken = hash('sha256', $token);

        $invitation = Invitation::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'email' => $request->string('email')->toString(),
            ],
            [
                'token' => $hashedToken,
                'invited_by' => $request->user()?->getKey(),
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
            ],
        );

        Notification::route('mail', $invitation->email)
            ->notify(new InvitationCreated($invitation, $token, $request->user()?->name));

        return back()->with('status', 'Invitation sent!');
    }
}
