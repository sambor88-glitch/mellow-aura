<?php

namespace App\Modules\Workshops;

use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\SitemapPages;

/**
 * Workshops: the price list page, and in the panel the price list and the calendar of dates.
 * Booking online comes after the holidays.
 */
class WorkshopsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes('workshops.index'));
    }
}
