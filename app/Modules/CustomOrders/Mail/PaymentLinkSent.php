<?php

namespace App\Modules\CustomOrders\Mail;

use App\Modules\CustomOrders\Models\PaymentLink;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use App\Modules\Shared\Support\Money;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The link to pay for a custom piece, with the amount, the quote and the date it is valid until (MA-144).
 */
class PaymentLinkSent extends QueuedMail implements HasEditableTexts
{
    public function __construct(public PaymentLink $link) {}

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'payment_link',
            label: 'Link do płatności — zamówienie indywidualne',
            when: 'Gdy wyślesz z panelu link do zapłaty zaliczki albo całości za zamówienie indywidualne.',
            lang: 'custom-orders::mail-texts.payment_link',
            placeholders: ['imie' => 'imię z formularza', 'kwota' => 'kwota do zapłaty'],
        );
    }

    public static function sample(string $locale): self
    {
        return new self((new PaymentLink)->forceFill([
            'token' => 'podglad',
            'amount' => 27_000,
            'expires_at' => now()->addWeek(),
        ])->setRelation('customOrderRequest', BriefConfirmed::sampleRequest()));
    }

    public function description(): string
    {
        return 'Link do płatności '.Money::format($this->link->amount).' — '.$this->link->customOrderRequest->name;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('payment_link', 'subject', ['imie' => $this->link->customOrderRequest->name, 'kwota' => Money::format($this->link->amount)]),
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        $idea = $this->link->customOrderRequest;

        return new Content(
            view: 'custom-orders::mail.payment-link',
            text: 'custom-orders::mail.payment-link-text',
            with: [
                'idea' => $idea,
                'amount' => Money::format($this->link->amount),
                // Only when it differs from the amount: half now, the rest before shipping.
                'quote' => $idea->quote !== null && $idea->quote !== $this->link->amount ? Money::format($idea->quote) : null,
                'url' => $this->link->url(),
                'validUntil' => $this->link->expires_at->locale('pl')->translatedFormat('j F'),
                'contactEmail' => app(Settings::class)->get('contact_email'),
                'city' => app(Settings::class)->get('footer_city'),
            ],
        );
    }
}
