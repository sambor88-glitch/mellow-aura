<?php

namespace App\Modules\Shared\Support;

use App\Modules\Localization\Support\Locales;

/**
 * Products in a Google Analytics e-commerce event. An item's id is the one in the Google Merchant Center feed, so
 * Analytics and Merchant Center speak about the same thing. Only what the shop sells goes in, never what a customer
 * typed: no text for a mug, no names on a voucher.
 */
final class AnalyticsItem
{
    /**
     * @return array<string, string|int|float>
     */
    public static function make(string $id, string $name, int $price, int $quantity = 1, ?string $variant = null, ?string $category = null): array
    {
        return array_filter([
            'item_id' => $id,
            'item_name' => $name,
            'item_brand' => MerchantFeed::BRAND,
            'item_category' => $category,
            'item_variant' => $variant,
            'price' => $price / 100,
            'quantity' => $quantity,
        ], fn (mixed $value) => $value !== null && $value !== '');
    }

    /**
     * An event's params: what the items are worth together, without delivery, as Analytics wants it — in the
     * currency of the page, which is the currency of every price on it.
     *
     * @param  int  $value  in grosze or cents
     * @param  list<array<string, string|int|float>>  $items
     * @param  array<string, string|int|float>  $extra
     * @return array<string, mixed>
     */
    public static function params(int $value, array $items, array $extra = []): array
    {
        return ['currency' => Locales::currency(), 'value' => $value / 100, ...$extra, 'items' => $items];
    }
}
