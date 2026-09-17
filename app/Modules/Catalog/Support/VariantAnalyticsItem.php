<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shared\Support\AnalyticsItem;

/**
 * A product size as a Google Analytics item, with the same id as in the Google Merchant Center feed.
 */
final class VariantAnalyticsItem
{
    /**
     * @return array<string, string|int|float>
     */
    public static function make(ProductVariant $variant, int $quantity = 1): array
    {
        return AnalyticsItem::make('v'.$variant->id, $variant->product->name, $variant->price_gross, $quantity, $variant->label ?: null, $variant->product->category?->name);
    }
}
