<?php

namespace App\Modules\Catalog\Enums;

/**
 * Who a gift is for, in the gift finder.
 */
enum Recipient: string
{
    case ForHer = 'for_her';
    case ForHim = 'for_him';
    case ForCouple = 'for_couple';

    public function label(): string
    {
        return match ($this) {
            self::ForHer => 'Dla niej',
            self::ForHim => 'Dla niego / taty',
            self::ForCouple => 'Dla pary',
        };
    }
}
