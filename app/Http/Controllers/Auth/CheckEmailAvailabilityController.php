<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CheckEmailAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'email' => ['required', 'email:filter'],
            'tenant' => ['required', 'string', 'max:63'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'message' => $validator->errors()->first() ?? 'E-mail invalide.',
            ], 422);
        }

        $email = (string) $request->query('email');
        $tenantSlug = Str::slug((string) $request->query('tenant'));

        if ($tenantSlug === '') {
            return response()->json([
                'available' => false,
                'message' => 'Veuillez saisir un sous-domaine.',
            ], 422);
        }

        $exists = User::query()
            ->where('tenant_id', $tenantSlug)
            ->where('email', $email)
            ->exists();

        return response()->json([
            'available' => ! $exists,
            'message' => $exists ? 'Adresse e-mail déjà utilisée.' : 'Adresse e-mail disponible.',
        ]);
    }
}
