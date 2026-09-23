<?php

namespace App\Modules\Localization\Routing;

use App\Modules\Localization\Support\Locales;
use Illuminate\Routing\UrlGenerator;

/**
 * route('shop.index') on an English page gives /en/shop, so no view or controller has to know the language.
 * A page without an English twin keeps its Polish address — a link never leads to a missing page.
 */
class LocalizedUrlGenerator extends UrlGenerator
{
    public function route($name, $parameters = [], $absolute = true)
    {
        return parent::route($this->localize($name), $parameters, $absolute);
    }

    /** The route exactly as named, whatever the language of the page — for hreflang and the switch. */
    public function exactRoute(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        return parent::route($name, $parameters, $absolute);
    }

    protected function localize(string $name): string
    {
        $locale = Locales::current();

        if ($locale === Locales::default() || str_starts_with($name, $locale.'.')) {
            return $name;
        }

        return $this->routes->getByName($locale.'.'.$name) !== null ? $locale.'.'.$name : $name;
    }
}
