<?php

namespace App\Modules\Shared\Support;

use Illuminate\Support\Facades\App;

/**
 * Amounts are integers in the currency's smallest unit (grosze, cents). Shown like the prototype (239,00 zł),
 * written for machines as a plain decimal (239.00).
 */
class Money
{
    /**
     * Złoty unless named: a euro amount is always asked for by its currency, so no złoty price ever shows under
     * a euro sign. Euro reads the English way on an English page (€39.00) and the Polish way elsewhere (39,00 €).
     */
    public static function format(int $minor, string $currency = 'PLN'): string
    {
        $polish = str_replace('.', ',', self::decimal($minor));

        return match ($currency) {
            'PLN' => $polish.' zł',
            'EUR' => App::getLocale() === 'en' ? '€'.self::decimal($minor) : $polish.' €',
            default => $polish.' '.$currency,
        };
    }

    public static function decimal(int $grosze): string
    {
        $sign = $grosze < 0 ? '-' : '';
        $grosze = abs($grosze);

        return $sign.intdiv($grosze, 100).'.'.str_pad((string) ($grosze % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * A price for a form field in the panel: "79" for whole złoty, "79,90" otherwise.
     */
    public static function input(int $grosze): string
    {
        return $grosze % 100 === 0 ? (string) intdiv($grosze, 100) : str_replace('.', ',', self::decimal($grosze));
    }

    /**
     * Grosze from a price someone typed — "79", "79,9" or "79.90" — without going through float.
     * Validate the format before calling it.
     */
    public static function parse(string $amount): int
    {
        [$zloty, $grosze] = array_pad(preg_split('/[.,]/', trim($amount), 2), 2, '');

        return (int) $zloty * 100 + (int) str_pad(substr($grosze, 0, 2), 2, '0');
    }
}
