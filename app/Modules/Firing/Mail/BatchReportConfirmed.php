<?php

namespace App\Modules\Firing\Mail;

use App\Modules\Firing\Models\FiringRequest;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's copy of her batch with the quote from the price list. The studio's address is not here —
 * Kasia sends it in her answer.
 */
class BatchReportConfirmed extends QueuedMail implements HasEditableTexts
{
    public function __construct(public FiringRequest $firingRequest) {}

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'batch_report_confirmed',
            label: 'Potwierdzenie zgłoszenia do wypału',
            when: 'Zaraz po wysłaniu formularza „Zgłoś wsad”, z wyceną z cennika.',
            lang: 'firing::mail-texts.batch_report_confirmed',
            placeholders: ['imie' => 'imię z formularza'],
        );
    }

    public static function sample(string $locale): self
    {
        return new self((new FiringRequest)->forceFill([
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'service_label' => 'Wypał biskwitowy',
            'quantity' => 3,
            'unit' => 'szt',
            'unit_price' => 1_500,
            'quote' => 4_500,
            'drop_off_on' => now()->addWeek(),
        ]));
    }

    public function description(): string
    {
        return 'Potwierdzenie zgłoszenia wsadu — '.$this->firingRequest->name;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('batch_report_confirmed', 'subject', ['imie' => $this->firingRequest->name]),
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
