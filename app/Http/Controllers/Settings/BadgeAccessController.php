<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\BadgePinRequest;
use App\Models\User;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BadgeAccessController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorizeAccess($request);

        $users = User::query()
            ->with('roles')
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get()
            ->map(function (User $user): array {
                $badgeCode = $user->badge_code;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name,
                    'badge_code' => $badgeCode,
                    'badge_qr_svg' => $badgeCode ? $this->renderBadgeQr($badgeCode) : null,
                    'pin_set' => (bool) $user->badge_pin,
                ];
            });

        return Inertia::render('settings/Badges', [
            'users' => $users,
        ]);
    }

    public function generateCode(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeUser($request, $user);

        $user->forceFill([
            'badge_code' => $this->generateBadgeCode($user->tenant_id),
        ])->save();

        return back()->with('success', 'Code badge généré.');
    }

    public function updatePin(BadgePinRequest $request, User $user): RedirectResponse
    {
        $this->authorizeAccess($request);
        $this->authorizeUser($request, $user);

        $user->forceFill([
            'badge_pin' => Hash::make((string) $request->input('pin')),
        ])->save();

        return back()->with('success', 'PIN badge mis à jour.');
    }

    public function download(Request $request, User $user): StreamedResponse|Response
    {
        $this->authorizeAccess($request);
        $this->authorizeUser($request, $user);

        if (! $user->badge_code) {
            abort(404);
        }

        $svg = $this->renderBadgeQr($user->badge_code);
        $filename = sprintf('badge-%s.svg', $user->badge_code);

        return response()->streamDownload(
            static fn () => print ($svg),
            $filename,
            ['Content-Type' => 'image/svg+xml'],
        );
    }

    private function authorizeAccess(Request $request): void
    {
        $roles = ['owner', 'manager', 'admin', 'superadmin'];
        abort_unless($request->user()?->hasAnyRole($roles), 403);
    }

    private function authorizeUser(Request $request, User $user): void
    {
        abort_unless((string) $user->tenant_id === (string) $request->user()?->tenant_id, 403);
    }

    private function generateBadgeCode(string $tenantId): string
    {
        $attempts = 0;

        do {
            $code = strtoupper(Str::random(10));
            $exists = User::query()
                ->where('tenant_id', $tenantId)
                ->where('badge_code', $code)
                ->exists();
            $attempts++;
        } while ($exists && $attempts < 10);

        if ($exists) {
            return (string) Str::uuid();
        }

        return $code;
    }

    private function renderBadgeQr(string $badgeCode): string
    {
        $payload = sprintf('serena-badge:%s', $badgeCode);
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_L,
            'scale' => 4,
            'imageBase64' => false,
        ]);

        return (new QRCode($options))->render($payload);
    }
}
