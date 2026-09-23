<?php

namespace App\Modules\MugConfigurator\Support;

use App\Modules\MugConfigurator\Cart\MugLine;
use App\Modules\Shared\Support\AnalyticsItem;

/**
 * A mug size from the configurator as a Google Analytics item, with the same id as in the Google Merchant Center
 * feed. The customer's text stays out.
 */
final class MugAnalyticsItem
{
    /**
     * @param  array{label: string, name: string, price: int}  $size  in the currency of the page, like the event
     * @return array<string, string|int|float>
     */
    public static function make(array $size, int $quantity = 1): array
    {
        return AnalyticsItem::make('mug-'.MugOptions::sizeKey($size['label']), MugLine::NAME, $size['price'], $quantity, $size['name']);
    }
}
