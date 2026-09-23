<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\OrderSummary;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's news that the parcel left the studio: what is in it and, when Kasia typed the number,
 * a link to follow it on InPost's site. Replies reach Kasia's contact address.
 */
class ParcelOnItsWay extends QueuedMail
{
    public function __construct(public Order $order) {}

    public function description(): string
    {
        return 'Wiadomość o wysłaniu zamówienia '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: 'Zamówienie '.$this->order->number.' jest w drodze',
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'checkout::mail.parcel-on-its-way',
            text: 'checkout::mail.parcel-on-its-way-text',
            with: app(OrderSummary::class)->for($this->order),
        );
    }
}
