<?php

namespace App\Modules\Firing\Mail;

use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's copy of her batch with the quote from the price list. The studio's address is not here —
 * Kasia sends it in her answer.
 */
class BatchReportConfirmed extends QueuedMail
{
    public function __construct(public FiringRequest $firingRequest) {}

    public function description(): string
    {
        return 'Potwierdzenie zgłoszenia wsadu — '.$this->firingRequest->name;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: 'Twój wsad do wypału — mam zgłoszenie',
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        $settings = app(Settings::class);

        return new Content(
            view: 'firing::mail.batch-report-confirmed',
            text: 'firing::mail.batch-report-confirmed-text',
            with: [
                'contactEmail' => $settings->get('contact_email'),
                'note' => $settings->get('text_kiln_note'),
                'city' => $settings->get('footer_city'),
            ],
        );
    }
}
