<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\OrderSummary;
use App\Modules\Settings\Settings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The summary the terms promise right after ordering (§5 ust. 4): what was ordered and that the contract comes
 * with the second mail, once the payment is in. A bank transfer gets the details to pay.
 */
class OrderAwaitingPayment extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: 'Podsumowanie zamówienia '.$this->order->number,
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'checkout::mail.order-awaiting-payment',
            text: 'checkout::mail.order-awaiting-payment-text',
            with: app(OrderSummary::class)->for($this->order),
        );
    }
}
