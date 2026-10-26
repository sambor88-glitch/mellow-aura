<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;

/**
 * Writes down which payment an unpaid order waits for, so the confirmation page knows to ask the gateway about it.
 * The Payments module calls this instead of writing to the orders table itself.
 *
 * A new BLIK code after a refused one makes the order wait again. A paid order is never touched.
 */
class AttachPaymentReference
{
    public function __invoke(Order $order, string $reference): Order
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return $order;
        }

        return tap($order)->update(['payment_provider_id' => $reference, 'payment_status' => PaymentStatus::Pending]);
    }
}
