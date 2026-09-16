<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\CatalogSitemap;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\BusinessStructuredData;
use App\Modules\Shared\Support\SitemapPages;

class CatalogServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(SitemapPages::class, fn (SitemapPages $pages) => $pages->add(new CatalogSitemap));
    }

    public function boot(): void
    {
        parent::boot();

        BusinessStructuredData::priceRangeUsing(ProductVariant::priceRange(...));
    }
}
