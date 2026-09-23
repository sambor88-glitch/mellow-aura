<?php

namespace App\Modules\Catalog\Enums;

/**
 * Optional product dimensions, stored under these keys in products.dimensions, in panel order.
 */
enum Dimension: string
{
    case Height = 'height_cm';
    case Width = 'width_cm';
    case Diameter = 'diameter_cm';
    case Circumference = 'circumference_cm';
    case Thickness = 'thickness_mm';
    case Capacity = 'capacity_ml';

    public function label(): string
    {
        return match ($this) {
            self::Height => 'Wysokość',
            self::Width => 'Szerokość',
            self::Diameter => 'Średnica',
            self::Circumference => 'Obwód',
            self::Thickness => 'Grubość',
            self::Capacity => 'Pojemność',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Thickness => 'mm',
            self::Capacity => 'ml',
            default => 'cm',
        };
    }
}
