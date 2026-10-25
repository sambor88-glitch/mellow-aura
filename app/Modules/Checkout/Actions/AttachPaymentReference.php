<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Models\Order;

/**
 * Writes down which payment an unpaid order waits for, so the confirmation page knows to ask the gateway about it.
 * The Payments module calls this instead of writing to the orders table itself.
 */
class AttachPaymentReference
{
    public function __invoke(Order $order, string $reference): Order
    {
        return tap($order)->update(['payment_provider_id' => $reference]);
    }
}
