<?php

namespace App\Modules\Checkout\Mail;

use App\Modules\Checkout\Mail\Concerns\InOrderLanguage;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\SampleOrder;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The one mail, a few days after the order is completed, asking the customer for a review on Kasia's Google
 * Business Profile (MA-329). A transactional note about her own order: no offer, no discount and no reward for
 * the review, which Google and UOKiK forbid. Replies reach Kasia's contact address.
 */
class ReviewRequest extends QueuedMail implements HasEditableTexts
{
    use InOrderLanguage;

    public function __construct(public Order $order, public string $reviewUrl)
    {
        $this->inOrderLanguage($order);
    }

    public static function editableTexts(): EditableTexts
    {
        return new EditableTexts(
            key: 'review_request',
            label: 'Prośba o opinię',
            when: 'Kilka dni po zakończeniu zamówienia — liczbę dni i link do opinii ustawiasz w Ustawieniach → Opinie.',
            lang: 'checkout::mail-texts.review_request',
            placeholders: ['numer' => 'numer zamówienia'],
            locales: ['pl', 'en'],
        );
    }

    /**
     * Without a saved link the preview points at the shop, so the button still shows where it sits.
     */
    public static function sample(string $locale): self
    {
        return new self(SampleOrder::make($locale), (string) app(Settings::class)->get('review_request_url', route('home')));
    }

    public function description(): string
    {
        return 'Prośba o opinię o zamówieniu '.$this->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('review_request', 'subject', ['numer' => $this->order->number]),
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: $this->template('review-request'),
            text: $this->template('review-request-text'),
            with: [
                'reviewUrl' => $this->reviewUrl,
                'contactEmail' => app(Settings::class)->get('contact_email'),
            ],
        );
    }
}
