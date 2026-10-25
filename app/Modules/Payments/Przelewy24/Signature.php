<?php

namespace App\Modules\Payments\Przelewy24;

/**
 * The `sign` field Przelewy24 asks for on every call: SHA-384 of a JSON object with the CRC key as its
 * last field. Each call signs different fields, always in the order the documentation lists them, and
 * the types matter — an amount sent as "1234" instead of 1234 gives another hash.
 */
final class Signature
{
    /**
     * @param  array<string, int|string|bool>  $fields  in the order the documentation lists them, without the CRC
     */
    public static function make(array $fields, string $crc): string
    {
        return hash('sha384', json_encode([...$fields, 'crc' => $crc], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
