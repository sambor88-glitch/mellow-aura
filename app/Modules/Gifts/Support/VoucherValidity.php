<?php

namespace App\Modules\Gifts\Support;

/**
 * How long a voucher is valid, in words: „rok”, „2 lata”, „6 miesięcy”.
 */
class VoucherValidity
{
    public static function label(int $months): string
    {
        $plural = fn (int $count, string $one, string $few, string $many) => $count === 1 ? $one : (
            in_array($count % 10, [2, 3, 4], true) && ! in_array($count % 100, [12, 13, 14], true) ? $count.' '.$few : $count.' '.$many
        );

        return $months > 0 && $months % 12 === 0
            ? $plural(intdiv($months, 12), 'rok', 'lata', 'lat')
            : $plural(max(1, $months), 'miesiąc', 'miesiące', 'miesięcy');
    }
}
