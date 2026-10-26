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
     * In the currency of the page, like the event it goes into (AnalyticsItem::params).
     *
     * @return array<string, string|int|float>
     */
    public static function make(ProductVariant $variant, int $quantity = 1): array
    {
        return AnalyticsItem::make('v'.$variant->id, $variant->product->name, (int) $variant->price(), $quantity, $variant->label ?: null, $variant->product->category?->name);
    }
}
