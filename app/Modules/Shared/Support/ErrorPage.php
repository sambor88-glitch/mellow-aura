<?php

namespace App\Modules\Shared\Support;

use App\Modules\Localization\Support\Locales;

/**
 * The language and the addresses of an error page (resources/views/errors). A 404 never reaches the middleware that
 * sets the language, so the page reads it from the address itself: /en/… is English while English is switched on.
 * Only the config and the lang files, never the database — the error may be exactly that the database is down.
 */
class ErrorPage
{
    public static function locale(): string
    {
        foreach (Locales::enabled() as $locale) {
            $prefix = config('localization.locales.'.$locale.'.prefix');

            if ($prefix !== '' && request()->is($prefix, $prefix.'/*')) {
                return $locale;
            }
        }

        return Locales::default();
    }

    public static function text(string $key): string
    {
        return __('shared::errors.'.$key, [], self::locale());
    }

    public static function home(): string
    {
        $prefix = config('localization.locales.'.self::locale().'.prefix');

        return url($prefix === '' ? '/' : '/'.$prefix);
    }

    public static function shop(): string
    {
        $locale = self::locale();

        return $locale === Locales::default()
            ? url('/sklep')
            : url(config('localization.locales.'.$locale.'.prefix').'/'.config('localization.paths.'.$locale)['shop.index']);
    }
}
