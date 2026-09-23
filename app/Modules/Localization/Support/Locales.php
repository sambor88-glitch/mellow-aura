<?php

namespace App\Modules\Localization\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;

/**
 * The languages of the site and the way between them. A page's twin in another language shares its route name;
 * only the default language goes without a prefix („shop.index” and „en.shop.index”).
 */
class Locales
{
    public static function default(): string
    {
        return config('localization.default');
    }

    /** @return list<string> the default language first, then the others that are switched on */
    public static function enabled(): array
    {
        $known = array_keys(config('localization.locales'));
        $on = array_intersect($known, config('localization.enabled'));

        return array_values(array_unique([self::default(), ...$on]));
    }

    public static function isEnabled(string $locale): bool
    {
        return in_array($locale, self::enabled(), true);
    }

    public static function current(): string
    {
        return App::getLocale();
    }

    public static function currency(?string $locale = null): string
    {
        return config('localization.locales.'.($locale ?? self::current()).'.currency');
    }

    public static function ogLocale(?string $locale = null): string
    {
        return config('localization.locales.'.($locale ?? self::current()).'.og');
    }

    /** The route name as the default language knows it: „en.shop.index” becomes „shop.index”. */
    public static function baseName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        foreach (self::enabled() as $locale) {
            if ($locale !== self::default() && str_starts_with($name, $locale.'.')) {
                return substr($name, strlen($locale) + 1);
            }
        }

        return $name;
    }

    /** The route name of a page in a language, or null when the page does not exist in it. */
    public static function nameFor(string $baseName, string $locale): ?string
    {
        $name = $locale === self::default() ? $baseName : $locale.'.'.$baseName;

        return self::isEnabled($locale) && app('router')->has($name) ? $name : null;
    }

    public static function has(string $baseName, string $locale): bool
    {
        return self::nameFor($baseName, $locale) !== null;
    }

    /**
     * The same page in every language it exists in, for hreflang. Empty when the page has no twin:
     * a single-language page has nothing to point to.
     *
     * @return array<string, string> locale => absolute URL
     */
    public static function alternates(Request $request): array
    {
        $route = $request->route();
        $base = $route instanceof Route ? self::baseName($route->getName()) : null;

        if ($base === null) {
            return [];
        }

        $urls = [];
        foreach (self::enabled() as $locale) {
            if ($name = self::nameFor($base, $locale)) {
                $urls[$locale] = app('url')->exactRoute($name, $route->parameters());
            }
        }

        return count($urls) > 1 ? $urls : [];
    }

    /**
     * Where the language switch leads from the current page: its twin, or the page named in
     * „localization.fallbacks”, or the home page of that language when the page has no name at all.
     */
    public static function switchUrl(Request $request, string $locale): string
    {
        $route = $request->route();
        $base = $route instanceof Route ? self::baseName($route->getName()) : null;

        if ($base !== null && $name = self::nameFor($base, $locale)) {
            return app('url')->exactRoute($name, $route->parameters());
        }

        // Route names hold dots, so the list is read whole — „fallbacks.content.b2b” would look for a nested key.
        $fallback = config('localization.fallbacks')[$base] ?? null;
        if ($fallback !== null && $name = self::nameFor($fallback, $locale)) {
            return app('url')->exactRoute($name);
        }

        return app('url')->exactRoute(self::nameFor('home', $locale) ?? 'home');
    }

    /** True when the switch from this page leads somewhere else than its own twin. */
    public static function switchFallsBack(Request $request, string $locale): bool
    {
        $route = $request->route();
        $base = $route instanceof Route ? self::baseName($route->getName()) : null;

        return $base === null || ! self::has($base, $locale);
    }

    /**
     * The language the browser asks for first among the ones the site speaks, from Accept-Language.
     * Never the visitor's country: a Polish customer on holiday in Spain still gets Polish.
     */
    public static function preferred(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $short = strtolower(substr($language, 0, 2));
            if (self::isEnabled($short)) {
                return $short;
            }
        }

        return null;
    }
}
