<?php

namespace App\Modules\Shared\Support;

/**
 * Amounts are integers in grosze; this is the only place that turns them into text.
 */
final class Money
{
    /**
     * 29900 → "299,00 zł", as zl() in the prototype. The space before "zł" is non-breaking.
     */
    public static function format(int $grosze): string
    {
        $sign = $grosze < 0 ? '-' : '';
        $grosze = abs($grosze);

        return sprintf("%s%d,%02d\u{00A0}zł", $sign, intdiv($grosze, 100), $grosze % 100);
    }
}
