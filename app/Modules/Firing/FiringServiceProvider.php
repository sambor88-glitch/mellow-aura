<?php

namespace App\Modules\Firing;

use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\SitemapPages;

/**
 * Firing other people's work: the price list page with the batch form and its quote, the editor in the panel
 * the mails about a reported batch and the list of batches in the panel, grouped into the weekly load.
 */
class FiringServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes('firing.index'));
    }
}
