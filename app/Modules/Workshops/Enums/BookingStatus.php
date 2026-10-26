<?php

namespace App\Modules\Workshops\Enums;

/**
 * Where a booking stands. Held seats count as taken until the hold runs out; confirmed seats always count.
 */
enum BookingStatus: string
{
    /** The order is placed and waits for the payment; the seats are kept for WorkshopBooking::HOLD_MINUTES. */
    case Held = 'held';

    /** Paid, the seats are hers. */
    case Confirmed = 'confirmed';

    /** The payment failed or never came; the seats are free again. */
    case Released = 'released';

    /** Paid after the hold ran out, when someone else had already taken the seats. Kasia returns the money. */
    case Overbooked = 'overbooked';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Czeka na płatność',
            self::Confirmed => 'Potwierdzony',
            self::Released => 'Zwolniony',
            self::Overbooked => 'Nadkomplet',
        };
    }
}
