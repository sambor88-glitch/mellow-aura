<?php

namespace App\Modules\Shared\Support;

/**
 * Amounts are integer grosze. Shown like the prototype (239,00 zł),
 * written for machines as a plain decimal (239.00).
 */
class Money
{
    public static function format(int $grosze): string
    {
        return str_replace('.', ',', self::decimal($grosze)).' zł';
    }

    public static function decimal(int $grosze): string
    {
        $sign = $grosze < 0 ? '-' : '';
        $grosze = abs($grosze);

        return $sign.intdiv($grosze, 100).'.'.str_pad((string) ($grosze % 100), 2, '0', STR_PAD_LEFT);
    }
}
