<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\OrderSummary;
use App\Modules\Shared\Mail\QueuedMail;
use App\Modules\Shared\Support\Money;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells Kasia a paid order is waiting, with everything she needs to pack it.
 * Replying writes straight to the customer.
 */
class NewOrderReceived extends QueuedMail
{
    public function __construct(public Order $order) {}

    public function description(): string
    {
        return 'Powiadomienie dla Ciebie o zamówieniu '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nowe zamówienie '.$this->order->number.' · '.Money::format($this->order->total_gross),
            replyTo: [new Address($this->order->email, $this->order->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'checkout::mail.new-order',
            text: 'checkout::mail.new-order-text',
            with: app(OrderSummary::class)->for($this->order),
        );
    }
}
