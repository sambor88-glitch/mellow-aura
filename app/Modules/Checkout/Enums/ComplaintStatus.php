<?php

namespace App\Modules\Checkout\Enums;

/**
 * A complaint waits for its answer until Kasia sends one from the panel.
 */
enum ComplaintStatus: string
{
    case Open = 'open';
    case Answered = 'answered';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Czeka na odpowiedź',
            self::Answered => 'Odpowiedziana',
        };
    }
}
