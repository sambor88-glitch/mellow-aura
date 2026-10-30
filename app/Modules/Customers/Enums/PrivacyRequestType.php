<?php

namespace App\Modules\Customers\Enums;

/**
 * The two GDPR requests „Klienci” carries out: a copy of everything about a person (art. 15 and 20) and
 * forgetting her (art. 17).
 */
enum PrivacyRequestType: string
{
    case Export = 'export';
    case Erasure = 'erasure';

    public function label(): string
    {
        return match ($this) {
            self::Export => 'Kopia danych',
            self::Erasure => 'Usunięcie danych',
        };
    }

    /**
     * The chip's colors, as on the order list.
     */
    public function chipClass(): string
    {
        return match ($this) {
            self::Export => 'bg-navy-soft text-navy',
            self::Erasure => 'bg-alert text-error',
        };
    }
}
