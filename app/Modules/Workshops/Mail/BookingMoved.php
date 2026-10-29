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
use App\Modules\Workshops\Support\BookingCalendar;
use App\Modules\Workshops\Support\BookingDetails;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Kasia moved a paid booking to another date (Actions\MoveBooking, MA-210): the new date with everything the
 * confirmation has — the studio address included, so the mail stands on its own — and the date it replaces.
 * The previous date comes as text: the queue loads the booking afresh, by then on its new date.
 * The attached event has the booking's UID and a higher SEQUENCE, so her calendar moves the event it already has (MA-268).
 */
class BookingMoved extends QueuedMail implements HasEditableTexts
{
    use BookingTexts, InOrderLanguage;

    /**
     * @param  string  $previous  „sob. 31 października, 11:00 – 13:00”
     */
    public function __construct(public WorkshopBooking $booking, public string $previous)
    {
        $this->inOrderLanguage($booking->order);
    }

    public static function editableTexts(): EditableTexts
    {
        return self::bookingTexts('booking_moved', 'Przeniesienie zapisu na inny termin', 'Gdy przeniesiesz opłacony zapis w panelu. Z nowym terminem, adresem pracowni i listą, co zabrać.');
    }

    public static function sample(string $locale): self
    {
        $booking = self::sampleBooking();

        return new self($booking, $booking->workshopDate->starts_at->subWeek()->locale('pl')->isoFormat('ddd[.] D MMMM').', 11:00 – 13:30');
    }

    public function description(): string
    {
        return 'Przeniesienie zapisu na warsztat na inny termin '.$this->booking->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('booking_moved', 'subject', $this->textFill()),
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $calendar = app(BookingCalendar::class);

        return [Attachment::fromData(fn () => $calendar->ics($this->booking, withAddress: true), BookingCalendar::FILE_NAME)->withMime(BookingCalendar::mime())];
    }

    public function content(): Content
    {
        return new Content(
            view: 'workshops::mail.booking-moved',
            text: 'workshops::mail.booking-moved-text',
            with: [...app(BookingDetails::class)->for($this->booking), 'previous' => $this->previous, 'textFill' => $this->textFill()],
        );
    }
}
