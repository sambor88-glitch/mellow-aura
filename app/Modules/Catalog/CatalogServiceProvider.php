<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\CatalogSitemap;
use App\Modules\Catalog\Support\ProductMerchantItems;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\BusinessStructuredData;
use App\Modules\Shared\Support\MerchantFeed;
use App\Modules\Shared\Support\SitemapPages;

class CatalogServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->add(new CatalogSitemap));
        $this->callAfterResolving(MerchantFeed::class, fn (MerchantFeed $feed) => $feed->add(fn () => $this->app->make(ProductMerchantItems::class)()));
    }

    public function boot(): void
    {
        parent::boot();

        BusinessStructuredData::priceRangeUsing(ProductVariant::priceRange(...));
    }
}
