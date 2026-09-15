<?php

namespace App\Modules\Shared\Support;

/**
 * Amounts are integer grosze. Formatted like the prototype: 239,00 zł.
 */
class Money
{
    public static function format(int $grosze): string
    {
        $sign = $grosze < 0 ? '-' : '';
        $grosze = abs($grosze);

        return $sign.intdiv($grosze, 100).','.str_pad((string) ($grosze % 100), 2, '0', STR_PAD_LEFT).' zł';
    }
}
