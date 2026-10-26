<?php

namespace App\Modules\CustomOrders\Mail;

use App\Modules\CustomOrders\Models\PaymentLink;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\QueuedMail;
use App\Modules\Shared\Support\Money;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The link to pay for a custom piece, with the amount, the quote and the date it is valid until (MA-144).
 */
class PaymentLinkSent extends QueuedMail
{
    public function __construct(public PaymentLink $link) {}

    public function description(): string
    {
        return 'Link do płatności '.Money::format($this->link->amount).' — '.$this->link->customOrderRequest->name;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: 'Link do płatności: '.Money::format($this->link->amount),
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
