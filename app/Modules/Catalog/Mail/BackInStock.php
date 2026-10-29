<?php

namespace App\Modules\Catalog\Mail;

use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The one mail someone asked for on a sold-out size: it is back on the shelf (MA-266). In the language of the page
 * she signed up on. It carries copies of the name and the link, because her row is gone by the time it is sent.
 */
class BackInStock extends QueuedMail implements HasEditableTexts
{
    public function __construct(
        string $locale,
        public string $productName,
        public ?string $variantLabel,
        public string $url,
        public string $recipient,
    ) {
        $this->locale($locale);
    }

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'back_in_stock',
            label: 'Znów na półce',
            when: 'Gdy dodasz sztuki rozmiaru, który był wyprzedany, do osób, które prosiły o wiadomość.',
            lang: 'catalog::mail-texts.back_in_stock',
            placeholders: ['produkt' => 'nazwa rzeczy i rozmiar'],
            locales: ['pl', 'en'],
        );
    }

    public static function sample(string $locale): self
    {
        return new self($locale, $locale === 'en' ? 'Mug “Not yet”' : 'Kubek „Jeszcze nie teraz”', '350 ml', url('/'), 'anna@example.com');
    }

    /** „Kubek „Jeszcze nie teraz” (350 ml)”. */
    public function itemName(): string
    {
        return $this->productName.($this->variantLabel ? ' ('.$this->variantLabel.')' : '');
    }

    public function description(): string
    {
        return 'Znów na półce: '.$this->itemName().' — do '.$this->recipient;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('back_in_stock', 'subject', ['produkt' => $this->itemName()]),
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'catalog::mail.back-in-stock',
            text: 'catalog::mail.back-in-stock-text',
            with: ['item' => $this->itemName()],
        );
    }
}
