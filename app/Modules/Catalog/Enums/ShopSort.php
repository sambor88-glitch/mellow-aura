<?php

namespace App\Modules\Catalog\Enums;

/**
 * Sorting on /sklep, passed as ?sort=. The canonical URL drops it.
 */
enum ShopSort: string
{
    case Recommended = 'recommended';
    case PriceAsc = 'price_asc';
    case PriceDesc = 'price_desc';
    case Name = 'name';

    public function label(): string
    {
        return match ($this) {
            self::Recommended => 'Polecane',
            self::PriceAsc => 'Cena rosnąco',
            self::PriceDesc => 'Cena malejąco',
            self::Name => 'Nazwa A–Z',
        };
    }
}
