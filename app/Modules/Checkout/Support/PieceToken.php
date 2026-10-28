<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Checkout\Models\OrderItem;

/**
 * The address of one bought piece, /k/{token}, printed as a QR code on its certificate and on the card in the
 * parcel (MA-234). Random and long enough not to be guessed — 31^12 ≈ 8 · 10^17 tokens — and short enough to
 * type from the card. No 0/o, 1/l/i: a token read off paper is never ambiguous.
 */
class PieceToken
{
    public const ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    public const LENGTH = 12;

    /** The route pattern: anything else is a 404 before the database is asked. */
    public const PATTERN = '['.self::ALPHABET.']{'.self::LENGTH.'}';

    public static function generate(): string
    {
        do {
            $token = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $token .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (OrderItem::query()->where('page_token', $token)->exists());

        return $token;
    }
}
