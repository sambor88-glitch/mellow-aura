<?php

namespace App\Modules\Workshops;

use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\SitemapPages;

/**
 * Workshops. Before the holidays: the price list page and its editor in the panel.
 * Booking dates and places come after the holidays.
 */
class WorkshopsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes('workshops.index'));
    }
}
