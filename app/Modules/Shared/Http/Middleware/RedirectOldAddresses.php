<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Support\OldAddresses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a visitor or a search engine from an old SumUp address to the new page with a 301, before routing,
 * so an old path that collides with a new route (GET /koszyk) redirects too. Tracking parameters travel along.
 */
class RedirectOldAddresses
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $target = OldAddresses::target($request->getPathInfo());

            if ($target !== null) {
                // The raw query keeps the parameters in their original order; getQueryString() would sort them.
                $query = (string) $request->server('QUERY_STRING');

                return redirect()->to($target.($query !== '' ? '?'.$query : ''), 301);
            }
        }

        return $next($request);
    }
}
