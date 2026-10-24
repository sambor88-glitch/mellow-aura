<?php

namespace App\Modules\Checkout\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Czeka na płatność',
            self::Paid => 'Opłacone',
            self::Failed => 'Płatność nieudana',
            self::Refunded => 'Zwrócone',
        };
    }
}
