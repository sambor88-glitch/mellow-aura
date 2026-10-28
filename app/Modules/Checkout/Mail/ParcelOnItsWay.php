<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Mail\Concerns\InOrderLanguage;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\OrderSummary;
use App\Modules\Checkout\Support\SampleOrder;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's news that the parcel left the studio: what is in it and, when Kasia typed the number,
 * a link to follow it on InPost's site. Replies reach Kasia's contact address.
 */
class ParcelOnItsWay extends QueuedMail implements HasEditableTexts
{
    use InOrderLanguage;

    public function __construct(public Order $order)
    {
        $this->inOrderLanguage($order);
    }

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'parcel_on_its_way',
            label: 'Paczka w drodze',
            when: 'Gdy zamówienie dostaje status „Wysłane” — z numerem przesyłki InPost.',
            lang: 'checkout::mail-texts.parcel_on_its_way',
            placeholders: ['numer' => 'numer zamówienia'],
            locales: ['pl', 'en'],
        );
    }

    public static function sample(string $locale): self
    {
        return new self(SampleOrder::make($locale));
    }

    public function description(): string
    {
        return 'Wiadomość o wysłaniu zamówienia '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('parcel_on_its_way', 'subject', ['numer' => $this->order->number]),
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
