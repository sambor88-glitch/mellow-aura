<?php

namespace App\Modules\Shared\Support;

use App\Modules\Settings\Settings;

/**
 * How many working days an item from the shelf takes to leave the studio, from the panel: „3–5 dni roboczych”.
 * The product page, the home page, the order confirmation and the data for Google all read it here.
 */
class DispatchTime
{
    /**
     * @return array{int, int}|null the fewest and the most working days, or null when the panel has none
     */
    public static function days(Settings $settings): ?array
    {
        $min = $settings->get('dispatch_days_min');
        $max = $settings->get('dispatch_days_max');

        if (! is_numeric($min) && ! is_numeric($max)) {
            return null;
        }

        $min = (int) (is_numeric($min) ? $min : $max);
        $max = (int) (is_numeric($max) ? $max : $min);

        return [min($min, $max), max($min, $max)];
    }

    /**
     * „3–5 dni roboczych”, „1 dzień roboczy”, or with $short „3–5 dni” — in the language of the page.
     */
    public static function label(Settings $settings, bool $short = false): ?string
    {
        if (($days = self::days($settings)) === null) {
            return null;
        }

        [$min, $max] = $days;
        $range = $min === $max ? (string) $max : $min.'–'.$max;

        if ($min === $max && $max === 1) {
            return __($short ? 'shared::dispatch.one_short' : 'shared::dispatch.one', ['range' => $range]);
        }

        $few = in_array($max % 10, [2, 3, 4], true) && ! in_array($max % 100, [12, 13, 14], true);

        return __($short ? 'shared::dispatch.many_short' : ($few ? 'shared::dispatch.few' : 'shared::dispatch.many'), ['range' => $range]);
    }
}
