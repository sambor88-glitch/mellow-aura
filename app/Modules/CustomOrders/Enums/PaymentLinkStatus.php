<?php

namespace App\Modules\CustomOrders\Enums;

/**
 * A payment link: waiting for the money, paid, or replaced by a newer link. An open link past its date is
 * expired (PaymentLink::isUsable), with no status of its own.
 */
enum PaymentLinkStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
