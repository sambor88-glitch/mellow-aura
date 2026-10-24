<?php

namespace App\Modules\Checkout\Enums;

enum ShippingMethod: string
{
    case ParcelLocker = 'parcel_locker';
    case Courier = 'courier';
    case StudioPickup = 'studio_pickup';

    public function label(): string
    {
        return match ($this) {
            self::ParcelLocker => 'InPost Paczkomat',
            self::Courier => 'Kurier InPost',
            self::StudioPickup => 'Odbiór w pracowni',
        };
    }
}
