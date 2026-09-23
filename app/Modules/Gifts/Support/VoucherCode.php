<?php

namespace App\Modules\Gifts\Support;

/**
 * Voucher codes like MA-7KQ2-9XHT. A voucher is worth money, so the code is random rather than
 * a running number that the next buyer could guess.
 */
class VoucherCode
{
    // Without 0/O and 1/I/L, which are easy to mix up when a code is read out on the phone or typed in.
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public static function generate(): string
    {
        $characters = '';

        for ($i = 0; $i < 8; $i++) {
            $characters .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'MA-'.substr($characters, 0, 4).'-'.substr($characters, 4);
    }
}
