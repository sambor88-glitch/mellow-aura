<?php

namespace App\Modules\Shared;

use App\Modules\Shared\Support\MerchantFeed;
use App\Modules\Shared\Support\SitemapPages;

class SharedServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SitemapPages::class);
        $this->app->singleton(MerchantFeed::class);
    }
}
