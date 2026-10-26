<?php

namespace App\Modules\Shared\Support;

/**
 * Polish tax numbers (NIP): ten digits, the last one a checksum of the others.
 */
class Nip
{
    private const WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    public static function digits(mixed $value): string
    {
        return (string) preg_replace('/\D+/', '', (string) $value);
    }

    /**
     * The tenth digit is the weighted sum of the first nine, modulo 11.
     */
    public static function isValid(mixed $value): bool
    {
        $digits = self::digits($value);

        if (strlen($digits) !== 10) {
            return false;
        }

        $numbers = array_map(intval(...), str_split($digits));
        $sum = array_sum(array_map(fn (int $weight, int $digit) => $weight * $digit, self::WEIGHTS, array_slice($numbers, 0, 9)));

        return $sum % 11 === $numbers[9];
    }
}
