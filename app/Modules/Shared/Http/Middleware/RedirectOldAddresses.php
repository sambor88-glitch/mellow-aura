<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Support\OldAddresses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a visitor or a search engine from an old SumUp address to the new page with a 301, before routing,
 * so an old path that collides with a new route (GET /koszyk) redirects too. An old path missing from the list
 * goes to the nearest section only when the new site has nothing there. Tracking parameters travel along.
 */
class RedirectOldAddresses
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $target = OldAddresses::target($request->getPathInfo());

        if ($target !== null) {
            return $this->redirect($request, $target);
        }

        $response = $next($request);

        // An old address missing from the list, like a SumUp category added after the list was taken.
        if ($response->getStatusCode() === 404 && ($fallback = OldAddresses::fallback($request->getPathInfo())) !== null) {
            return $this->redirect($request, $fallback);
        }

        return $response;
    }

    private function redirect(Request $request, string $target): Response
    {
        // The raw query keeps the parameters in their original order; getQueryString() would sort them.
        $query = (string) $request->server('QUERY_STRING');

        return redirect()->to($target.($query !== '' ? '?'.$query : ''), 301);
    }
}
