<?php

namespace App\Modules\Gifts\Enums;

/**
 * Where a paid gift choice card stands (MA-235), in Kasia's words in the panel.
 */
enum GiftChoiceStatus: string
{
    /** Goes to the person it is for by e-mail on a day still to come. */
    case Scheduled = 'scheduled';
    case Waiting = 'waiting';
    case Chosen = 'chosen';
    /** Nobody chose in time and Kasia has not decided yet. */
    case Expired = 'expired';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Czeka na wysyłkę',
            self::Waiting => 'Czeka na wybór',
            self::Chosen => 'Wybrany',
            self::Expired => 'Po terminie',
            self::Resolved => 'Załatwione',
        };
    }

    /** The chip's colors from the mellowaura-panel skill. */
    public function chipClass(): string
    {
        return match ($this) {
            self::Scheduled => 'bg-navy-soft text-navy',
            self::Waiting => 'bg-sand-dark text-lead',
            self::Chosen, self::Resolved => 'bg-linen text-label',
            self::Expired => 'bg-alert text-error',
        };
    }
}
