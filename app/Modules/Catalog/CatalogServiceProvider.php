<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\Shared\Support\BusinessStructuredData;

class CatalogServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        BusinessStructuredData::priceRangeUsing(ProductVariant::priceRange(...));
    }
}
