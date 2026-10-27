<?php

namespace App\Modules\Workshops\Mail;

use App\Modules\Checkout\Mail\Concerns\InOrderLanguage;
use App\Modules\Content\Support\LegalPdf;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\QueuedMail;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Support\BookingDetails;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The customer's confirmation once the seats are paid: which workshop, when, for how many people and the exact
 * studio address — the only place the address ever appears. It takes the place of the shop's order confirmation,
 * so she gets one mail. The terms of sale come attached, confirming the contract on a durable medium; there is no
 * withdrawal form, since a workshop on a set date has no right of withdrawal (art. 38 ust. 1 pkt 12 ustawy o prawach konsumenta).
 * Workshops exist only in Polish; the language still comes from the order, like every mail to a customer.
 */
class BookingConfirmed extends QueuedMail
{
    use InOrderLanguage;

    public function __construct(public WorkshopBooking $booking)
    {
        $this->inOrderLanguage($booking->order);
    }

    public function description(): string
    {
        return 'Potwierdzenie zapisu na warsztat '.$this->booking->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');
        $date = $this->booking->workshopDate;

        return new Envelope(
            subject: 'Do zobaczenia w pracowni · '.$date->dayLabel().', '.$date->starts_at->format('H:i'),
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(LegalPdf::class);

        return [Attachment::fromData(fn () => $pdf->terms(), 'MellowAura-regulamin.pdf')->withMime('application/pdf')];
    }

    public function content(): Content
    {
        return new Content(
            view: 'workshops::mail.booking-confirmed',
            text: 'workshops::mail.booking-confirmed-text',
            with: app(BookingDetails::class)->for($this->booking),
        );
    }
}
