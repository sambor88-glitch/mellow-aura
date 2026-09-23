<?php

namespace App\Modules\Checkout\Events;

use App\Modules\Checkout\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Kasia marked the order as sent in the panel, with the tracking number if she has it.
 */
class OrderShipped implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
