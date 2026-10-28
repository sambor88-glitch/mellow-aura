<?php

namespace App\Modules\Gifts\Enums;

/**
 * One use of an amount voucher's balance (MA-211). The money leaves the balance when the order is placed, so two
 * orders with the same code can never spend more than it holds; it comes back if the order is never paid.
 */
enum RedemptionStatus: string
{
    /** Taken off the balance for an order that waits for its payment. */
    case Held = 'held';

    /** The order is paid: spent for good. */
    case Used = 'used';

    /** Back on the balance: the order was never paid, or Kasia gave the money back to the voucher. */
    case Returned = 'returned';

    /** What Kasia reads in the voucher's history in the panel. */
    public function label(): string
    {
        return match ($this) {
            self::Held => 'czeka na dopłatę',
            self::Used => 'wykorzystane',
            self::Returned => 'wróciło na voucher',
        };
    }
}
