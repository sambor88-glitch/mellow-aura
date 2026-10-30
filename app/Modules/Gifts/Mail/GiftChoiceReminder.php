<?php

namespace App\Modules\Gifts\Mail;

use App\Modules\Gifts\Models\GiftChoice;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A reminder that a gift choice card still waits (MA-235), 14 and 3 days before its last day. A card that went by
 * e-mail reminds the person it is for; a printed one reminds the buyer, who knows whom to tell.
 */
class GiftChoiceReminder extends QueuedMail implements HasEditableTexts
{
    public function __construct(public GiftChoice $choice, public bool $toBuyer = false) {}

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'gift_choice_reminder',
            label: 'Przypomnienie o karcie wyboru',
            when: '14 i 3 dni przed końcem terminu na wybór — do obdarowanej, a przy karcie do druku do osoby, która ją kupiła.',
            lang: 'gifts::mail-texts.gift_choice_reminder',
            placeholders: ['kod' => 'kod karty', 'data' => 'ostatni dzień na wybór'],
        );
    }

    public static function sample(string $locale): self
    {
        return new self(GiftChoiceSample::make());
    }

    public function description(): string
    {
        return 'Przypomnienie o karcie wyboru '.$this->choice->code;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('gift_choice_reminder', 'subject', GiftChoiceArrived::fill($this->choice)),
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'gifts::mail.gift-choice-reminder',
            text: 'gifts::mail.gift-choice-reminder-text',
            with: [
                'fill' => GiftChoiceArrived::fill($this->choice),
                'variant' => $this->toBuyer ? 'buyer' : null,
                'url' => $this->choice->code ? $this->choice->chooseUrl() : route('gift-choice.index'),
                'contactEmail' => app(Settings::class)->get('contact_email'),
            ],
        );
    }
}
