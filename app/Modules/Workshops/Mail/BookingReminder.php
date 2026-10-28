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
use App\Modules\Workshops\Support\BookingDetails;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The day before the workshop: when, for how many people and the address again, so she does not search for the
 * first mail. Queued by Actions\SendReminders.
 */
class BookingReminder extends QueuedMail implements HasEditableTexts
{
    use BookingTexts, InOrderLanguage;

    public function __construct(public WorkshopBooking $booking)
    {
        $this->inOrderLanguage($booking->order);
    }

    public static function editableTexts(): EditableTexts
    {
        return self::bookingTexts('booking_reminder', 'Przypomnienie o warsztacie', 'Dzień przed warsztatem.');
    }

    public static function sample(string $locale): self
    {
        return new self(self::sampleBooking());
    }

    public function description(): string
    {
        return 'Przypomnienie o warsztacie '.$this->booking->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: app(MailTexts::class)->text('booking_reminder', 'subject', $this->textFill()),
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'workshops::mail.booking-reminder',
            text: 'workshops::mail.booking-reminder-text',
            with: [...app(BookingDetails::class)->for($this->booking), 'textFill' => $this->textFill()],
        );
    }
}
