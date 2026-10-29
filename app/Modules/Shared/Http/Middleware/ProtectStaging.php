<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the staging site to Kasia and the people building it: with STAGING_PASSWORD set, every page asks for
 * a login and password (HTTP basic auth). Without it, locally and in production, the site is open.
 *
 * Forge's health check and Przelewy24's payment notification come from servers that know no password, so they
 * pass; the notification proves itself with a signature. So does the product file for Meta and Pinterest, which
 * holds only what the shop shows anyway. The 401 page says noindex too, as it never reaches NoIndex.
 */
class ProtectStaging
{
    /** @var list<string> */
    private const OPEN = ['up', 'przelewy24/powiadomienie', 'social-catalog.xml'];

    public function handle(Request $request, Closure $next): Response
    {
        $password = (string) config('staging.password');

        if ($password === '' || $request->is(self::OPEN) || $this->knows($request, $password)) {
            return $next($request);
        }

        return response('Staging — dostęp tylko z hasłem.', 401, [
            'WWW-Authenticate' => 'Basic realm="MellowAura staging", charset="UTF-8"',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function knows(Request $request, string $password): bool
    {
        return hash_equals((string) config('staging.user'), (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());
    }
}
