<?php

namespace App\Modules\Shared\Support;

/**
 * Polish plural forms: 1 produkt · 2–4 produkty · 5+ produktów (also 12–14 and 22–24 rules).
 */
final class Plural
{
    public static function form(int $count, string $one, string $few, string $many): string
    {
        $count = abs($count);

        if ($count === 1) {
            return $one;
        }

        $lastDigit = $count % 10;
        $lastTwo = $count % 100;

        return $lastDigit >= 2 && $lastDigit <= 4 && ($lastTwo < 12 || $lastTwo > 14) ? $few : $many;
    }

    /**
     * "3 warianty", "5 wariantów".
     */
    public static function count(int $count, string $one, string $few, string $many): string
    {
        return $count.' '.self::form($count, $one, $few, $many);
    }
}
