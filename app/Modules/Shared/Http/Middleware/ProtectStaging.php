<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the staging site private: strangers, crawlers and link previews get a password prompt instead of the shop.
 * Active only when STAGING_PASSWORD is set. Stripe's webhook and Forge's health check cannot type a password, so they
 * pass; the webhook proves itself with its own signature.
 */
class ProtectStaging
{
    private const OPEN_PATHS = ['stripe/webhook', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        $password = (string) config('staging.password');

        if ($password === '' || $request->is(...self::OPEN_PATHS)) {
            return $next($request);
        }

        $authorized = hash_equals((string) config('staging.user'), (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());

        return $authorized
            ? $next($request)
            : response('Staging — dostęp tylko z hasłem.', 401, ['WWW-Authenticate' => 'Basic realm="MellowAura staging"']);
    }
}
