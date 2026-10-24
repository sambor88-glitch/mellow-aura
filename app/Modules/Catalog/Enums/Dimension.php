<?php

namespace App\Modules\Catalog\Enums;

/**
 * Optional product dimensions, keys of products.dimensions (DIMS in the prototype).
 */
enum Dimension: string
{
    case Height = 'height';
    case Width = 'width';
    case Diameter = 'diameter';
    case Circumference = 'circumference';
    case Thickness = 'thickness';
    case Capacity = 'capacity';

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

    /**
     * 8.5 → "8,5 cm".
     */
    public function format(int|float|string $value): string
    {
        return str_replace('.', ',', (string) $value).' '.$this->unit();
    }
}
