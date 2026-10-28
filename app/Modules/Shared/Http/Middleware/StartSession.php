<?php

namespace App\Modules\Shared\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as BaseStartSession;

/**
 * Laravel's session, except that a page the browser renders ahead of a click (SpeculationRules, MA-237) reads
 * the session but never writes it. The cart lives in the session file: a prerender that started before
 * „Dodaj do koszyka” and ended after it would write the old cart back. Not writing also keeps flash messages
 * and the „previous page” for the page the customer really sees.
 *
 * Chromium marks such a request with `Sec-Purpose: prefetch;prerender`; Laravel's own prefetch() wants the bare
 * word `prefetch`, so it misses it.
 */
class StartSession extends BaseStartSession
{
    public static function isSpeculative(Request $request): bool
    {
        return $request->prefetch() || str_contains(strtolower((string) $request->headers->get('Sec-Purpose')), 'prefetch');
    }

    protected function saveSession($request)
    {
        if (! self::isSpeculative($request)) {
            parent::saveSession($request);
        }
    }
}
