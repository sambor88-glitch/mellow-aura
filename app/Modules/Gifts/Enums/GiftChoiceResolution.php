<?php

namespace App\Modules\Gifts\Enums;

/**
 * What Kasia decided for a card nobody chose on in time (MA-235).
 */
enum GiftChoiceResolution: string
{
    case PlainMug = 'plain_mug';
    case Voucher = 'voucher';

    public function label(): string
    {
        return match ($this) {
            self::PlainMug => 'Kubek bez napisu',
            self::Voucher => 'Zamieniona na voucher',
        };
    }
}
