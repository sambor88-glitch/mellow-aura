<?php

namespace App\Modules\CustomOrders\Mail;

use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's copy of her idea. The photos are not sent back — she has them, and the list says how many came.
 */
class BriefConfirmed extends QueuedMail
{
    public function __construct(public CustomOrderRequest $customOrderRequest) {}

    public function description(): string
    {
        return 'Potwierdzenie zamówienia indywidualnego — '.$this->customOrderRequest->name;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: 'Mam Twój pomysł — odpiszę ze szkicem i wyceną',
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        $settings = app(Settings::class);

        return new Content(
            view: 'custom-orders::mail.brief-confirmed',
            text: 'custom-orders::mail.brief-confirmed-text',
            with: [
                'contactEmail' => $settings->get('contact_email'),
                'city' => $settings->get('footer_city'),
            ],
        );
    }
}
