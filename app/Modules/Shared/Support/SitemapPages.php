<?php

namespace App\Modules\Shared\Support;

use App\Modules\Localization\Support\Locales;
use Closure;
use Illuminate\Support\Facades\Route;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * What goes into sitemap.xml. Each module adds its pages in its provider, so a new page or module
 * needs no change here. The map is built on request, so it always follows the products in the panel.
 *
 * A page that exists in two languages is listed in both, each entry pointing to all of them (and x-default to
 * Polish), the same set the page itself names in hreflang. A Polish-only page is listed once, alone.
 */
class SitemapPages
{
    /** @var list<Closure(Sitemap): void> */
    private array $sources = [];

    /**
     * @param  callable(Sitemap): void  $source
     */
    public function add(callable $source): static
    {
        $this->sources[] = $source(...);

        return $this;
    }

    /**
     * Pages without parameters, by route name. A route that doesn't exist is skipped.
     */
    public function routes(string ...$names): static
    {
        return $this->add(function (Sitemap $sitemap) use ($names) {
            foreach ($names as $name) {
                if (Route::has($name)) {
                    $sitemap->add(self::versions($name));
                }
            }
        });
    }

    /**
     * One entry per language the page exists in. $configure adds what the entry needs besides its address —
     * photos, the date of the last change — and runs in the entry's language.
     *
     * @param  array<int|string, mixed>  $parameters
     * @param  (callable(Url, string): void)|null  $configure  receives the entry and its locale
     * @return list<Url>
     */
    public static function versions(string $name, array $parameters = [], ?callable $configure = null): array
    {
        $urls = Locales::versions(Locales::baseName($name), $parameters);
        $entries = [];

        foreach ($urls as $locale => $address) {
            $entry = Url::create($address);

            if (count($urls) > 1) {
                foreach ($urls as $alternateLocale => $alternate) {
                    $entry->addAlternate($alternate, $alternateLocale);
                }

                $entry->addAlternate($urls[Locales::default()] ?? reset($urls), 'x-default');
            }

            if ($configure !== null) {
                Locales::within($locale, fn () => $configure($entry, $locale));
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    public function build(): Sitemap
    {
        $sitemap = Sitemap::create();

        foreach ($this->sources as $source) {
            $source($sitemap);
        }

        return $sitemap;
    }
}
