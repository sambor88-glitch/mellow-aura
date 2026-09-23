<?php

namespace App\Modules\Localization\Http\Middleware;

use App\Modules\Localization\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of a page comes from its route: an English twin carries „locale” => „en” in its action,
 * everything else is Polish. Never from the visitor's country or a stored guess.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route()?->getAction('locale') ?? Locales::default();

        App::setLocale(Locales::isEnabled($locale) ? $locale : Locales::default());

        return $next($request);
    }
}
