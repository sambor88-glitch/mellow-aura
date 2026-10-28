<?php

use App\Http\Middleware\NoIndex;
use App\Modules\Localization\Support\Locales;
use App\Modules\Shared\Http\Middleware\KeepShortWords;
use App\Modules\Shared\Http\Middleware\ProtectStaging;
use App\Modules\Shared\Http\Middleware\RedirectOldAddresses;
use App\Modules\Shared\Http\Middleware\SecurityHeaders;
use App\Modules\Shared\Http\Middleware\StartSession;
use App\Modules\Shared\Support\Cloudflare;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as LaravelStartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // First of all: without the staging password nothing else runs, not even a redirect.
        $middleware->prepend(ProtectStaging::class);
        $middleware->append(NoIndex::class);
        $middleware->append(RedirectOldAddresses::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(KeepShortWords::class);
        // A page rendered ahead of a click reads the session but never writes it back (MA-237).
        $middleware->web(replace: [LaravelStartSession::class => StartSession::class]);
        // The customer's own address behind Cloudflare, so limits count per customer (see Cloudflare).
        $middleware->trustProxies(at: Cloudflare::PROXIES);
        // Przelewy24 has no session and no token; its notification proves itself with a signature instead.
        $middleware->validateCsrfTokens(except: ['przelewy24/powiadomienie']);
        // Written by the browser on a click of the language switch, so it cannot be encrypted (see Locales::CHOICE_COOKIE).
        $middleware->encryptCookies(except: [Locales::CHOICE_COOKIE]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
