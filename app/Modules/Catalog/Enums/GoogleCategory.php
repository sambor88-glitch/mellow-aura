<?php

namespace App\Modules\Catalog\Enums;

/**
 * The kinds of things the studio makes, with their ids in Google's product taxonomy
 * (https://www.google.com/basepages/producttype/taxonomy-with-ids.pl-PL.txt). Without one Google guesses the kind itself.
 */
enum GoogleCategory: int
{
    case Mugs = 2169;
    case CupsAndSaucers = 6049;
    case Plates = 3553;
    case Bowls = 3498;
    case ServingPlatters = 3802;
    case CakeStands = 4372;
    case Vases = 602;
    case IncenseHolders = 4741;
    case JewelryHolders = 5974;
    case Jewelry = 188;
    case HairTies = 1483;
    case Headbands = 1662;
    case CosmeticBags = 108;
    case PencilCases = 3062;
    case GiftCards = 53;

    public function label(): string
    {
        return match ($this) {
            self::Mugs => 'Kubki',
            self::CupsAndSaucers => 'Filiżanki i spodki',
            self::Plates => 'Talerze',
            self::Bowls => 'Miski',
            self::ServingPlatters => 'Półmiski',
            self::CakeStands => 'Patery',
            self::Vases => 'Wazony',
            self::IncenseHolders => 'Kadzielnice',
            self::JewelryHolders => 'Podstawki i pudełka na biżuterię',
            self::Jewelry => 'Biżuteria',
            self::HairTies => 'Gumki do włosów i scrunchies',
            self::Headbands => 'Opaski do włosów',
            self::CosmeticBags => 'Kosmetyczki',
            self::PencilCases => 'Piórniki',
            self::GiftCards => 'Vouchery i bony podarunkowe',
        };
    }
}
