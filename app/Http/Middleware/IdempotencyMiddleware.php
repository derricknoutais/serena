<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-Idempotency-Key');
        $user = $request->user();

        if (! $key || ! $user) {
            return $next($request);
        }

        $tenantId = $user->tenant_id;
        $hash = $this->hashRequest($request);

        $existing = DB::table('idempotency_keys')
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if ($existing->request_hash !== $hash) {
                return response()->json([
                    'message' => 'Requête en conflit pour cette clé d’idempotence.',
                ], Response::HTTP_CONFLICT);
            }

            return new JsonResponse(
                json_decode($existing->response_body, true),
                (int) $existing->response_status
            );
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            DB::table('idempotency_keys')->insert([
                'tenant_id' => $tenantId,
                'key' => $key,
                'request_hash' => $hash,
                'response_body' => json_encode($response->getData(true)),
                'response_status' => $response->getStatusCode(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $response;
    }

    private function hashRequest(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->method(),
            $request->path(),
            json_encode($request->all()),
        ]));
    }
}
