<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [HandleAppearance::class, HandleInertiaRequests::class, AddLinkHeadersForPreloadedAssets::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TenantCouldNotBeIdentifiedOnDomainException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(
                    [
                        'message' => 'This company does not exist.',
                    ],
                    Response::HTTP_NOT_FOUND,
                );
            }

            return Inertia::render('errors/TenantNotFound', [
                'domain' => $request->getHost(),
                'app_url' => config('app.url'),
                'message' => 'This company does not exist.',
            ])
                ->toResponse($request)
                ->setStatusCode(Response::HTTP_NOT_FOUND);
        });
    })
    ->create();
