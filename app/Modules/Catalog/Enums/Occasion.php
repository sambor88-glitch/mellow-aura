<?php

namespace App\Modules\Catalog\Enums;

/**
 * Occasions for the gift finder, in the order the panel shows them.
 */
enum Occasion: string
{
    case Birthday = 'birthday';
    case MothersDay = 'mothers_day';
    case Wedding = 'wedding';
    case Housewarming = 'housewarming';
    case Anniversary = 'anniversary';
    case Christmas = 'christmas';
    case SelfGift = 'self_gift';

    public function label(): string
    {
        return match ($this) {
            self::Birthday => 'Urodziny',
            self::MothersDay => 'Dzień Matki',
            self::Wedding => 'Ślub',
            self::Housewarming => 'Parapetówka',
            self::Anniversary => 'Rocznica',
            self::Christmas => 'Święta',
            self::SelfGift => 'Dla siebie',
        };
    }
}
