<?php

namespace App\Modules\Firing;

use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\SitemapPages;

/**
 * Firing other people's work. Before the holidays: the price list page and its editor in the panel.
 * The batch form and its list in the panel come after the holidays.
 */
class FiringServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes('firing.index'));
    }
}
