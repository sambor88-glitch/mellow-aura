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
 * The summary the terms promise right after ordering (§5 ust. 4): what was ordered and that the contract comes
 * with the second mail, once the payment is in. A bank transfer gets the details to pay.
 */
class OrderAwaitingPayment extends QueuedMail implements HasEditableTexts
{
    use InOrderLanguage;

    public function __construct(public Order $order)
    {
        $this->inOrderLanguage($order);
    }

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'order_awaiting_payment',
            label: 'Podsumowanie zamówienia',
            when: 'Zaraz po złożeniu zamówienia, zanim płatność dojdzie — także przy przelewie tradycyjnym, z danymi do przelewu.',
            lang: 'checkout::mail-texts.order_awaiting_payment',
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
        return 'Podsumowanie zamówienia '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('order_awaiting_payment', 'subject', ['numer' => $this->order->number]),
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->template('order-awaiting-payment'),
            text: $this->template('order-awaiting-payment-text'),
            with: app(OrderSummary::class)->for($this->order),
        );
    }
}
