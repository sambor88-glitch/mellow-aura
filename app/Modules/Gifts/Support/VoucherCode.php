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

    /**
     * A code as typed — lower case, with spaces, without dashes or the MA- in front — in the printed form.
     * Anything that cannot be a code comes back as typed, upper-cased, and is simply not found.
     */
    public static function normalize(string $typed): string
    {
        $characters = (string) preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper($typed));
        $characters = strlen($characters) === 10 && str_starts_with($characters, 'MA') ? substr($characters, 2) : $characters;

        return strlen($characters) === 8 ? 'MA-'.substr($characters, 0, 4).'-'.substr($characters, 4) : $characters;
    }
}
