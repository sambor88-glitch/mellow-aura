<?php

namespace App\Modules\Checkout\Enums;

/**
 * What an accepted complaint ends with, as §13 of the terms describes it.
 */
enum ComplaintRemedy: string
{
    case Repair = 'repair';
    case Replacement = 'replacement';
    case PriceReduction = 'price_reduction';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Repair => 'Naprawa',
            self::Replacement => 'Wymiana na nową sztukę',
            self::PriceReduction => 'Obniżenie ceny',
            self::Refund => 'Zwrot pieniędzy — odstąpienie od umowy',
        };
    }

    public function sentence(): string
    {
        return match ($this) {
            self::Repair => 'Naprawię produkt. Odbiór i odesłanie są na mój koszt.',
            self::Replacement => 'Wymienię produkt na nową sztukę. Odbiór i wysyłka nowej są na mój koszt.',
            self::PriceReduction => 'Obniżam cenę produktu. Kwotę obniżki zwrócę w ciągu 14 dni.',
            self::Refund => 'Przyjmuję odstąpienie od umowy. Zapłaconą kwotę zwrócę w ciągu 14 dni.',
        };
    }
}
