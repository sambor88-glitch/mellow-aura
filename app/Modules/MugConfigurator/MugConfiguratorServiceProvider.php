<?php

namespace App\Modules\MugConfigurator;

use App\Modules\Cart\LineTypes;
use App\Modules\MugConfigurator\Cart\MugLine;
use App\Modules\MugConfigurator\Cart\MugLines;
use App\Modules\MugConfigurator\Support\MugMerchantItems;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\MerchantFeed;
use App\Modules\Shared\Support\SitemapPages;

class MugConfiguratorServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(LineTypes::class, fn (LineTypes $types) => $types->register(MugLine::TYPE, MugLines::class));
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->routes('mug.index'));
        $this->callAfterResolving(MerchantFeed::class, fn (MerchantFeed $feed) => $feed->add(fn () => $this->app->make(MugMerchantItems::class)()));
    }
}
