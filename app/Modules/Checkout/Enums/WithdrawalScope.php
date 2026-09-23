<?php

namespace App\Modules\Checkout\Enums;

enum WithdrawalScope: string
{
    case Whole = 'whole';
    case Part = 'part';

    public function label(): string
    {
        return match ($this) {
            self::Whole => 'Całe zamówienie',
            self::Part => 'Część zamówienia',
        };
    }
}
