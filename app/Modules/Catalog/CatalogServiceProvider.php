<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Queries\OfferPriceRange;
use App\Modules\Shared\Contracts\PriceRange;
use App\Modules\Shared\ModuleServiceProvider;

class CatalogServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PriceRange::class, OfferPriceRange::class);
    }
}
