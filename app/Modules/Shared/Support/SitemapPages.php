<?php

namespace App\Modules\Shared\Support;

use Closure;
use Illuminate\Support\Facades\Route;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * What goes into sitemap.xml. Each module adds its pages in its provider, so a new page or module
 * needs no change here. The map is built on request, so it always follows the products in the panel.
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
                    $sitemap->add(Url::create(route($name)));
                }
            }
        });
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
