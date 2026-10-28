<?php

namespace App\Modules\Gifts\Enums;

/**
 * Why a code typed in at the checkout pays nothing (MA-211). The checkout words it for the customer.
 */
enum BalanceRefusal
{
    /** No voucher has this code. Said the same way however close the code was, so codes cannot be guessed. */
    case Unknown;

    /** A workshop voucher: it books seats, not an amount. */
    case Workshop;

    /** Nothing left on it. */
    case Spent;

    /** Past its valid_until. */
    case Expired;

    /** Typed in a second time on the same order. */
    case Twice;
}
