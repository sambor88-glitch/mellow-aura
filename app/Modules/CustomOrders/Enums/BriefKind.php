<?php

namespace App\Modules\CustomOrders\Enums;

/**
 * „Co to ma być?” in the custom order form, after the list in the prototype. The service pages open the form
 * with their kind chosen, through ?usluga=<slug>.
 */
enum BriefKind: string
{
    case Mugs = 'mugs';
    case Tableware = 'tableware';
    case OccasionGift = 'occasion_gift';
    case Decorative = 'decorative';
    case Scarf = 'scarf';
    case Imprint = 'imprint';
    case Other = 'other';

    public static function fromSlug(?string $slug): ?self
    {
        foreach (self::cases() as $kind) {
            if ($kind->slug() === $slug) {
                return $kind;
            }
        }

        return null;
    }

    /** The kind's part of a link from another page, e.g. /zamowienia-indywidualne?usluga=apaszka. */
    public function slug(): string
    {
        return match ($this) {
            self::Mugs => 'kubki',
            self::Tableware => 'serwis',
            self::OccasionGift => 'prezent',
            self::Decorative => 'forma-dekoracyjna',
            self::Scarf => 'apaszka',
            self::Imprint => 'odcisk',
            self::Other => 'inne',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Mugs => 'Kubki z napisem',
            self::Tableware => 'Serwis obiadowy albo do kawy',
            self::OccasionGift => 'Prezent na ślub, chrzciny, rocznicę',
            self::Decorative => 'Forma dekoracyjna albo rzeźba',
            self::Scarf => 'Opaska z Twojej apaszki',
            self::Imprint => 'Odcisk Twojej rośliny',
            self::Other => 'Coś innego',
        };
    }
}
