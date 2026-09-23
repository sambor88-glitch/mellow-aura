<?php

namespace App\Modules\Admin\Enums;

/**
 * The two levels of access from the specification: the owner sees everything, a helper only the orders to pack and send.
 */
enum Role: string
{
    case Owner = 'owner';
    case Helper = 'helper';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Właścicielka — cały panel',
            self::Helper => 'Pomoc przy zamówieniach',
        };
    }
}
