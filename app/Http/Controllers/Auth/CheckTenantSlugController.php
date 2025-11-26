<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

class CheckTenantSlugController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $rawSlug = (string) $request->query('slug', '');
        $slug = Str::slug($rawSlug);

        if ($slug === '') {
            return response()->json([
                'available' => false,
                'message' => 'Please enter a valid subdomain.',
            ], 422);
        }

        if (Str::length($slug) > 63) {
            return response()->json([
                'available' => false,
                'message' => 'The subdomain must not be greater than 63 characters.',
            ], 422);
        }

        $domain = sprintf('%s.%s', $slug, config('app.url_host', 'saas-template.test'));

        $isTaken = Tenant::whereKey($slug)->exists()
            || Domain::where('domain', $domain)->exists();

        return response()->json([
            'available' => ! $isTaken,
            'slug' => $slug,
            'domain' => $domain,
            'message' => $isTaken ? 'This subdomain is already taken.' : 'This subdomain is available.',
        ]);
    }
}
