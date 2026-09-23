<?php

namespace App\Modules\Checkout\Events;

use App\Modules\Checkout\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order has just been paid. Fired once per order and only after the payment transaction commits,
 * so a listener never acts on a payment that could still roll back.
 */
class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
