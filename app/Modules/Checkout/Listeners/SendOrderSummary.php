<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\Checkout\Events\OrderPlaced;
use App\Modules\Checkout\Mail\OrderAwaitingPayment;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the summary right after ordering. A mail that cannot be queued is reported and never stops the payment.
 */
class SendOrderSummary
{
    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing('items');

        rescue(fn () => Mail::to(new Address($order->email, $order->name))->send(new OrderAwaitingPayment($order)));
    }
}
