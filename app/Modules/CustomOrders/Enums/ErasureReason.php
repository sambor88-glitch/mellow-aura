<?php

namespace App\Modules\CustomOrders\Enums;

/**
 * Why an idea lost the customer's data and photos: the privacy policy no longer keeps them (MA-312), or she asked
 * to be forgotten (MA-241).
 */
enum ErasureReason: string
{
    case Retention = 'retention';
    case Request = 'request';

    /**
     * The end of the line in the panel, after „Dane i zdjęcia usunięte <date>”.
     */
    public function label(): string
    {
        return match ($this) {
            self::Retention => 'minął czas przechowywania',
            self::Request => 'na prośbę klientki',
        };
    }
}
