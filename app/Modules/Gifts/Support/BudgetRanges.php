<?php

namespace App\Modules\Gifts\Support;

use App\Modules\Settings\Settings;
use Illuminate\Support\Collection;

/**
 * The budget chips in „Szukam prezentu”, from the panel (gift_budget_ranges, in grosze). A range's key goes
 * into the address in whole złoty, e.g. ?budget=100-200 or ?budget=400- for the open one.
 */
class BudgetRanges
{
    /**
     * @return Collection<int, array{key: string, label: string, min: int, max: ?int}>
     */
    public static function from(Settings $settings): Collection
    {
        return collect((array) $settings->get('gift_budget_ranges', []))
            ->filter(fn (mixed $range) => is_array($range) && is_numeric($range['min_gross'] ?? null))
            ->map(function (array $range) {
                $min = max(0, (int) $range['min_gross']);
                $max = is_numeric($range['max_gross'] ?? null) ? (int) $range['max_gross'] : null;
                $zloty = fn (int $grosze) => intdiv($grosze, 100);

                return [
                    'key' => $zloty($min).'-'.($max === null ? '' : $zloty($max)),
                    'label' => match (true) {
                        $max === null => 'powyżej '.$zloty($min).' zł',
                        $min === 0 => 'do '.$zloty($max).' zł',
                        default => $zloty($min).'–'.$zloty($max).' zł',
                    },
                    'min' => $min,
                    'max' => $max,
                ];
            })
            ->values();
    }

    /**
     * Like the prototype: a range from zero includes its top price, every other one starts above its bottom,
     * so a price on the border belongs to one range only.
     *
     * @param  array{min: int, max: ?int}  $range
     */
    public static function contains(array $range, int $price): bool
    {
        $belowTop = $range['max'] === null || $price <= $range['max'];

        return $range['min'] <= 0 ? $belowTop : $price > $range['min'] && $belowTop;
    }
}
