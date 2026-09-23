<?php

use App\Http\Middleware\NoIndex;
use App\Modules\Shared\Http\Middleware\RedirectOldAddresses;
use App\Modules\Shared\Http\Middleware\SecurityHeaders;
use App\Modules\Shared\Support\Cloudflare;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(NoIndex::class);
        $middleware->append(RedirectOldAddresses::class);
        $middleware->append(SecurityHeaders::class);
        // The customer's own address behind Cloudflare, so limits count per customer (see Cloudflare).
        $middleware->trustProxies(at: Cloudflare::PROXIES);
        // Stripe has no session and no token; its calls prove themselves with a signature instead.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
