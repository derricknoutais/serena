<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Spatie\Permission\PermissionRegistrar;

class TenantLogoutResponse implements LogoutResponseContract
{
    public function __construct(private PermissionRegistrar $permissions) {}

    public function toResponse($request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->permissions->setPermissionsTeamId(null);

        return redirect()->route('home');
    }
}
