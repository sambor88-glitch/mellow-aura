<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Mail\Concerns\InOrderLanguage;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\OrderSummary;
use App\Modules\Checkout\Support\SampleOrder;
use App\Modules\Content\Support\LegalPdf;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's confirmation once the payment is in: what was bought, for how much,
 * where it goes and what happens next. The terms of sale and the model withdrawal form come
 * attached, so the contract is confirmed on a durable medium. Replies reach Kasia's contact address.
 */
class OrderConfirmed extends QueuedMail implements HasEditableTexts
{
    use InOrderLanguage;

    public function __construct(public Order $order)
    {
        $this->inOrderLanguage($order);
    }

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'order_confirmed',
            label: 'Potwierdzenie zamówienia',
            when: 'Gdy płatność dojdzie: co kupiła, za ile, dokąd idzie paczka. Z regulaminem i formularzem odstąpienia w załącznikach.',
            lang: 'checkout::mail-texts.order_confirmed',
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
        return 'Potwierdzenie zamówienia '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('order_confirmed', 'subject', ['numer' => $this->order->number]),
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(LegalPdf::class);

        return [
            Attachment::fromData(fn () => $pdf->terms(), 'MellowAura-regulamin.pdf')->withMime('application/pdf'),
            Attachment::fromData(fn () => $pdf->withdrawalForm($this->order->number, $this->order->created_at), 'MellowAura-formularz-odstapienia.pdf')->withMime('application/pdf'),
        ];
    }

    public function content(): Content
    {
        return new Content(
            view: $this->template('order-confirmed'),
            text: $this->template('order-confirmed-text'),
            with: app(OrderSummary::class)->for($this->order),
        );
    }
}
