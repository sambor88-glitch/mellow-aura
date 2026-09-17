<?php

namespace App\Modules\Checkout\Listeners;

use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Checkout\Mail\NewOrderReceived;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Mail\OrderUnavailable;
use App\Modules\Checkout\Support\OrderSummary;
use App\Modules\Settings\Settings;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the customer's confirmation and Kasia's notice about the order; the queue worker sends them and repeats
 * a failed attempt. A mail that cannot be queued is reported and never undoes a paid order.
 */
class SendOrderEmails
{
    public function __construct(private Settings $settings, private OrderSummary $summary) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order->loadMissing('items');

        // With every piece bought by someone else first there is nothing to confirm, only the payment to return.
        $customerMail = $this->summary->for($order)['nothingLeft'] ? new OrderUnavailable($order) : new OrderConfirmed($order);
        rescue(fn () => Mail::to(new Address($order->email, $order->name))->send($customerMail));

        if ($owner = $this->settings->get('contact_email')) {
            rescue(fn () => Mail::to($owner)->send(new NewOrderReceived($order)));
        }
    }
}
