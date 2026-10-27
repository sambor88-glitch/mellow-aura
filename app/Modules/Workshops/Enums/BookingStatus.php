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

    /** Cancelled by Kasia in the panel; the seats are free again and a paid booking waits for its refund. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Held => 'Czeka na płatność',
            self::Confirmed => 'Potwierdzony',
            self::Released => 'Zwolniony',
            self::Overbooked => 'Nadkomplet',
            self::Cancelled => 'Anulowany',
        };
    }

    /**
     * The chip's colors, in the panel's palette: waiting, fine, a problem, or out of the count.
     */
    public function chipClass(): string
    {
        return match ($this) {
            self::Held => 'bg-rose text-ink',
            self::Confirmed => 'bg-navy-soft text-navy',
            self::Overbooked => 'bg-alert text-error',
            self::Released, self::Cancelled => 'bg-linen text-label',
        };
    }

    /**
     * Whether the money came in for these seats, so cancelling them leaves a refund to make.
     */
    public function isPaid(): bool
    {
        return in_array($this, [self::Confirmed, self::Overbooked], true);
    }
}
