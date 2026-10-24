<?php

namespace App\Modules\Checkout\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nowe',
            self::InProgress => 'W realizacji',
            self::Shipped => 'Wysłane',
            self::Completed => 'Zrealizowane',
            self::Cancelled => 'Anulowane',
        };
    }
}
