<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\Checkout\Events\OrderShipped;
use App\Modules\Checkout\Mail\ParcelOnItsWay;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * Tells the customer her parcel left the studio. A mail that cannot be queued is reported and never undoes the status.
 */
class SendShippingNotice
{
    public function handle(OrderShipped $event): void
    {
        $order = $event->order->loadMissing('items');

        rescue(fn () => Mail::to(new Address($order->email, $order->name))->send(new ParcelOnItsWay($order)));
    }
}
