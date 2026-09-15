<?php

namespace App\Modules\Checkout\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nowe',
            self::InProgress => 'W realizacji',
        };
    }
}
