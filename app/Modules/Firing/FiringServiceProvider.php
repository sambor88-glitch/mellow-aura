<?php

namespace App\Modules\Firing;

use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\SitemapPages;

/**
 * Firing other people's work: the price list page with the batch form and its quote, the editor in the panel
 * and the mails about a reported batch. The list of batches in the panel comes with MA-149.
 */
class FiringServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes('firing.index'));
    }
}
