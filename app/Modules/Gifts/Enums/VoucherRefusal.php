<?php

namespace App\Modules\Gifts\Enums;

/**
 * Why a code typed in to book a workshop does not book it. The page that asked words it for the customer.
 */
enum VoucherRefusal
{
    /** No voucher has this code. Said the same way however close the code was, so codes cannot be guessed. */
    case Unknown;

    /** Already used for a booking. */
    case Redeemed;

    /** Past its valid_until. */
    case Expired;

    /** For a different workshop than the date's. */
    case OtherWorkshop;

    /** An amount voucher, or one Kasia has not given a workshop yet: she books it by hand. */
    case NoWorkshop;
}
