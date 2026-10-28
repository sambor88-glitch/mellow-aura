<?php

namespace App\Modules\Workshops\Mail;

use App\Modules\Checkout\Mail\Concerns\InOrderLanguage;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Shared\Mail\HasEditableTexts;
use App\Modules\Shared\Mail\MailTexts;
use App\Modules\Shared\Mail\QueuedMail;
use App\Modules\Workshops\Mail\Concerns\BookingTexts;
use App\Modules\Workshops\Models\WorkshopBooking;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Kasia cancelled a paid booking in the panel (CancelBooking): which workshop, the refund within 14 days, and
 * an answer to this mail for another date instead. A booking paid with a voucher gets the voucher back instead of
 * a refund; the mail says how it stood when it was given back, since the code may book again before the queue sends it.
 */
class BookingCancelled extends QueuedMail implements HasEditableTexts
{
    use BookingTexts, InOrderLanguage;

    /** @var ?array{code: string, valid_until: string, expired: bool} */
    public ?array $returnedVoucher;

    public function __construct(public WorkshopBooking $booking)
    {
        $this->inOrderLanguage($booking->order);
        $voucher = $booking->voucher;
        $this->returnedVoucher = $voucher === null ? null : [
            'code' => $voucher->code,
            'valid_until' => $voucher->valid_until->toDateString(),
            'expired' => $voucher->isExpired(),
        ];
    }

    public static function editableTexts(): EditableTexts
    {
        return self::bookingTexts('booking_cancelled', 'Anulowanie zapisu na warsztat', 'Gdy anulujesz zapis w panelu. Zwrot wpłaty albo voucher opisuje się sam.', ['subject', 'intro']);
    }

    public static function sample(string $locale): self
    {
        return new self(self::sampleBooking());
    }

    public function description(): string
    {
        return 'Anulowanie zapisu na warsztat — mail do klientki '.$this->booking->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('booking_cancelled', 'subject', $this->textFill()),
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    public function content(): Content
    {
        $settings = app(Settings::class);

        return new Content(
            view: 'workshops::mail.booking-cancelled',
            text: 'workshops::mail.booking-cancelled-text',
            with: [
                'order' => $this->booking->order,
                'date' => $this->booking->workshopDate,
                'voucher' => $this->returnedVoucher === null ? null : [
                    ...$this->returnedVoucher,
                    'valid_until' => CarbonImmutable::parse($this->returnedVoucher['valid_until'])->translatedFormat('j F Y'),
                ],
                'contactPhone' => $settings->get('contact_phone'),
                'textFill' => $this->textFill(),
                'city' => $settings->get('footer_city'),
            ],
        );
    }
}
