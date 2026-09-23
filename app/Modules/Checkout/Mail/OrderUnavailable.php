<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Mail\Concerns\InOrderLanguage;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\OrderSummary;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent instead of the confirmation when every piece of a paid order was bought by someone else first: nothing is
 * sent, and the whole payment comes back (§5 ust. 5 of the terms).
 */
class OrderUnavailable extends QueuedMail
{
    use InOrderLanguage;

    public function __construct(public Order $order)
    {
        $this->inOrderLanguage($order);
    }

    public function description(): string
    {
        return 'Zwrot wpłaty za zamówienie '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: __('checkout::mail.subject.unavailable', ['number' => $this->order->number]),
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->template('order-unavailable'),
            text: $this->template('order-unavailable-text'),
            with: app(OrderSummary::class)->for($this->order),
        );
    }
}
