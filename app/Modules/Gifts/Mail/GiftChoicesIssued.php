<?php

namespace App\Modules\Gifts\Mail;

use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\SampleOrder;
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
use Illuminate\Support\Collection;

/**
 * The paid gift choice cards as PDFs, to the buyer (MA-235), in a mail of their own without prices, like the vouchers.
 * A card that goes by e-mail says to whom and on which day.
 */
class GiftChoicesIssued extends QueuedMail implements HasEditableTexts
{
    /**
     * @param  Collection<int, GiftChoice>  $choices
     */
    public function __construct(public Order $order, public Collection $choices) {}

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'gift_choices',
            label: 'Karta „Podaruj wybór” w PDF',
            when: 'Gdy płatność za kartę wyboru dojdzie — do osoby, która ją kupiła, z PDF-em bez cen.',
            lang: 'gifts::mail-texts.gift_choices',
            placeholders: ['kod' => 'kod karty (kilka po przecinku)'],
        );
    }

    public static function sample(string $locale): self
    {
        $order = SampleOrder::make('pl');

        return new self($order, collect([GiftChoiceSample::make()]));
    }

    public function description(): string
    {
        return 'Karty wyboru z zamówienia '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('gift_choices', 'subject', $this->fill(), $this->choices->count() > 1 ? 'many' : null),
            replyTo: $contact ? [new Address($contact, 'Kasia z MellowAury')] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'gifts::mail.gift-choices',
            text: 'gifts::mail.gift-choices-text',
            with: ['fill' => $this->fill(), 'contactEmail' => app(Settings::class)->get('contact_email')],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return $this->choices
            ->map(fn (GiftChoice $choice) => Attachment::fromData(fn () => app(GiftChoicePdf::class)->render($choice), GiftChoicePdf::filename($choice))->withMime('application/pdf'))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function fill(): array
    {
        return ['kod' => $this->choices->pluck('code')->join(', ')];
    }
}
