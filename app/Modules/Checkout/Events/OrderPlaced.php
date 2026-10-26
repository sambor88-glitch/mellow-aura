<?php

namespace App\Modules\Checkout\Events;

use App\Modules\Checkout\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order is placed and its payment has started — not rejected on the spot. The customer gets the summary now
 * and the confirmation once the payment is in (§5 ust. 4 of the terms).
 */
class OrderPlaced
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
