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
 * The customer's news that the parcel left the studio: what is in it and, when Kasia typed the number,
 * a link to follow it on InPost's site. Replies reach Kasia's contact address.
 */
class ParcelOnItsWay extends QueuedMail
{
    use InOrderLanguage;

    public function __construct(public Order $order)
    {
        $this->inOrderLanguage($order);
    }

    public function description(): string
    {
        return 'Wiadomość o wysłaniu zamówienia '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: __('checkout::mail.subject.parcel', ['number' => $this->order->number]),
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->template('parcel-on-its-way'),
            text: $this->template('parcel-on-its-way-text'),
            with: app(OrderSummary::class)->for($this->order),
        );
    }
}
