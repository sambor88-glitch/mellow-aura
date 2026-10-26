<?php

namespace App\Modules\Catalog\Enums;

enum CategoryGroup: string
{
    case Ceramics = 'ceramics';
    case Crafts = 'crafts';
    case Workshops = 'workshops';

    public function label(): string
    {
        return match ($this) {
            self::Ceramics => 'Ceramika',
            self::Crafts => 'Rękodzieło',
            self::Workshops => 'Warsztaty',
        };
    }
}
