<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Facades\Route;

/**
 * Addresses of the old SumUp shop that mellow-aura.com passed through until the launch, taken from its
 * sitemap on 16.09.2026. After the domain switch they arrive here and go permanently to the same thing
 * on the new site. Paths that already match, like /produkt/wazony or /kontakt, need no entry.
 */
class OldAddresses
{
    /** @var array<string, array{string, array<string, string>}> old path => [route name, parameters] */
    private const TARGETS = [
        '/produkty' => ['shop.index', []],
        '/koszyk' => ['shop.index', []],
        '/kategoria/ceramika' => ['shop.index', []],
        '/kategoria/tekstylia' => ['shop.category', ['category' => 'szycie']],
        '/produkt/zestaw-4-filizanek-z-talerzykami' => ['product.show', ['product' => 'zestaw-4-filizanek']],
        '/produkt/scrunchies-w-3-rozmiarach' => ['product.show', ['product' => 'scrunchies']],
        '/produkt/kubki-malowane-recznie' => ['product.show', ['product' => 'kubki-malowane']],
        '/strona/https-mellow-aura-com-tworczosc-ceramika-tekstylia-handmade' => ['home', []],
        '/strona/https-mellow-aura-com-ceramika-handmade-naturalna' => ['shop.index', []],
        '/strona/https-mellow-aura-com-tekstylia-handmade-scrunchies-opaski' => ['shop.category', ['category' => 'szycie']],
        '/strona/o-mnie' => ['content.about', []],
        '/strona/pracownia' => ['content.studio', []],
        '/strona/warsztaty' => ['workshops.index', []],
        '/strona/galeria-warsztaty-ceramiczne-krakow' => ['workshops.index', []],
        '/strona/warunki-korzystania' => ['content.terms', []],
        '/strona/polityka-prywatnosci' => ['content.privacy', []],
        '/polityka-cookies' => ['content.privacy', []],
    ];

    /**
     * @var array<string, string> old path prefix => route name, for an old address missing from TARGETS that
     *                            would otherwise end in a 404. Only prefixes the new site doesn't use: an unknown
     *                            /produkt/… stays a 404, because new product addresses share that prefix.
     */
    private const FALLBACKS = [
        '/kategoria/' => 'shop.index',
        '/strona/' => 'home',
    ];

    /**
     * The new address for an old path, or null when the path isn't an old one or its page is gone.
     */
    public static function target(string $path): ?string
    {
        $key = '/'.trim(mb_strtolower(rawurldecode($path)), '/');
        [$name, $parameters] = self::TARGETS[$key] ?? [null, []];

        return $name !== null && Route::has($name) ? route($name, $parameters) : null;
    }

    /**
     * The page for an old path that found nothing on the new site, by the part of the shop it came from.
     */
    public static function fallback(string $path): ?string
    {
        $key = '/'.trim(mb_strtolower(rawurldecode($path)), '/');

        foreach (self::FALLBACKS as $prefix => $name) {
            if (str_starts_with($key, $prefix) && Route::has($name)) {
                return route($name);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        return array_keys(self::TARGETS);
    }
}
