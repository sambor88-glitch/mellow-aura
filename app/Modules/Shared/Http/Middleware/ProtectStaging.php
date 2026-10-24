<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the staging site private and out of search engines.
 *
 * Active only when STAGING_PASSWORD is set. Every response, including the
 * 401 challenge, carries X-Robots-Tag: noindex.
 */
class ProtectStaging
{
    public function handle(Request $request, Closure $next): Response
    {
        $password = (string) config('staging.password');

        if ($password === '') {
            return $next($request);
        }

        $authorized = hash_equals((string) config('staging.user'), (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());

        $response = $authorized
            ? $next($request)
            : response('Staging — dostęp tylko z hasłem.', 401, ['WWW-Authenticate' => 'Basic realm="MellowAura staging"']);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
