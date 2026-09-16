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
 * The customer's confirmation once the payment is in: what was bought, for how much,
 * where it goes and what happens next. Replies reach Kasia's contact address.
 */
class OrderConfirmed extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: 'Zamówienie '.$this->order->number.' jest opłacone',
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'checkout::mail.order-confirmed',
            text: 'checkout::mail.order-confirmed-text',
            with: app(OrderSummary::class)->for($this->order),
        );
    }
}
