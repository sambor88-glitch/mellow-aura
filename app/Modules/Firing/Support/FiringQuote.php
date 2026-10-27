<?php

namespace App\Modules\Firing\Support;

/**
 * The quote for a batch from a price list row: a price per kilogram or per piece times the amount, or the row's
 * flat price. Any other unit has no quote — Kasia prices it herself. The form shows the same sum before sending
 * (resources/js/firing.js).
 */
class FiringQuote
{
    /** Units priced by an amount the customer types in. */
    public const BY_AMOUNT = ['kg', 'piece'];

    /**
     * @param  array{price_gross: int, unit: ?string}  $row
     * @param  ?string  $quantity  e.g. „2,5”; kilograms take two decimals, pieces a whole number
     */
    public static function for(array $row, ?string $quantity): ?int
    {
        if ($row['unit'] === null) {
            return $row['price_gross'];
        }

        if (! in_array($row['unit'], self::BY_AMOUNT, true) || $quantity === null) {
            return null;
        }

        // In hundredths, so 2,5 kg × 30 zł is counted in whole numbers and rounded to the grosz.
        return intdiv($row['price_gross'] * self::hundredths($quantity) + 50, 100);
    }

    public static function hundredths(string $quantity): int
    {
        [$whole, $fraction] = array_pad(preg_split('/[.,]/', trim($quantity), 2), 2, '');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
