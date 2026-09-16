<?php

namespace App\Modules\Content;

use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\SitemapPages;

class ContentServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes(
            'home', 'content.about', 'content.studio', 'content.scarf', 'content.imprint', 'content.b2b',
            'content.faq', 'content.contact', 'content.terms', 'content.privacy',
        ));
    }
}
