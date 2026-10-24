<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shared\Contracts\PriceRange;
use Illuminate\Database\Eloquent\Builder;

/**
 * priceRange for the Organization markup, counted from what is on the shelf now.
 */
class OfferPriceRange implements PriceRange
{
    public function bounds(): ?array
    {
        $row = ProductVariant::query()
            ->where('stock', '>', 0)
            ->whereHas('product', fn (Builder $product) => $product->where('is_published', true))
            ->toBase()
            ->selectRaw('min(price_gross) as min_price, max(price_gross) as max_price')
            ->first();

        if ($row?->min_price === null) {
            return null;
        }

        return [(int) $row->min_price, (int) $row->max_price];
    }
}
