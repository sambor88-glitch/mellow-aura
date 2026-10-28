<?php

namespace App\Modules\CustomOrders\Mail;

use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's copy of her idea. The photos are not sent back — she has them, and the list says how many came.
 */
class BriefConfirmed extends QueuedMail implements HasEditableTexts
{
    public function __construct(public CustomOrderRequest $customOrderRequest) {}

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'brief_confirmed',
            label: 'Potwierdzenie zamówienia indywidualnego',
            when: 'Zaraz po wysłaniu formularza „Zamówienia indywidualne”, z opisem pomysłu.',
            lang: 'custom-orders::mail-texts.brief_confirmed',
            placeholders: ['imie' => 'imię z formularza'],
        );
    }

    public static function sample(string $locale): self
    {
        return new self(self::sampleRequest());
    }

    /**
     * A made-up request, never saved, for the previews in the panel.
     */
    public static function sampleRequest(): CustomOrderRequest
    {
        return (new CustomOrderRequest)->forceFill([
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'kind' => BriefKind::Mugs,
            'quantity' => '6',
            'needed_by' => 'na święta',
            'description' => 'Sześć kubków na prezent dla rodziny, każdy z innym imieniem.',
            'quote' => 54_000,
        ]);
    }

    public function description(): string
    {
        return 'Potwierdzenie zamówienia indywidualnego — '.$this->customOrderRequest->name;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('brief_confirmed', 'subject', ['imie' => $this->customOrderRequest->name]),
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
