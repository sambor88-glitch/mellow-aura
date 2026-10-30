<?php

namespace App\Modules\Gifts\Mail;

use App\Modules\Gifts\Models\GiftChoice;
use App\Modules\Gifts\Support\GiftChoicePdf;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The gift choice card to the person it is for, on the day the buyer picked (MA-235): who it is from, the
 * dedication, the link to choose on and the last day, with the card as a PDF.
 */
class GiftChoiceArrived extends QueuedMail implements HasEditableTexts
{
    public function __construct(public GiftChoice $choice) {}

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'gift_choice_arrived',
            label: 'Karta wyboru do obdarowanej',
            when: 'W dniu wybranym przez kupującą — do osoby, która dostaje kubek, z linkiem do wyboru napisu.',
            lang: 'gifts::mail-texts.gift_choice_arrived',
            placeholders: ['kod' => 'kod karty', 'data' => 'ostatni dzień na wybór'],
        );
    }

    public static function sample(string $locale): self
    {
        return new self(GiftChoiceSample::make());
    }

    public function description(): string
    {
        return 'Karta wyboru '.$this->choice->code.' do '.$this->choice->recipient_email;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('gift_choice_arrived', 'subject', self::fill($this->choice)),
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'gifts::mail.gift-choice-arrived',
            text: 'gifts::mail.gift-choice-arrived-text',
            with: ['fill' => self::fill($this->choice), 'url' => $this->choice->code ? $this->choice->chooseUrl() : route('gift-choice.index'), 'contactEmail' => app(Settings::class)->get('contact_email')],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [Attachment::fromData(fn () => app(GiftChoicePdf::class)->render($this->choice), GiftChoicePdf::filename($this->choice))->withMime('application/pdf')];
    }

    /**
     * @return array<string, string>
     */
    public static function fill(GiftChoice $choice): array
    {
        return ['kod' => (string) $choice->code, 'data' => (string) $choice->choose_by?->translatedFormat('j F Y')];
    }
}
