<?php

namespace App\Modules\Workshops\Mail;

use App\Modules\Checkout\Mail\Concerns\InOrderLanguage;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\QueuedMail;
use App\Modules\Workshops\Models\WorkshopBooking;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The payment came after the hold ran out and the date is full: no address, only the apology and what happens
 * with the money. Kasia hears about it from the alert ConfirmBooking sends.
 */
class BookingOverbooked extends QueuedMail
{
    use InOrderLanguage;

    public function __construct(public WorkshopBooking $booking)
    {
        $this->inOrderLanguage($booking->order);
    }

    public function description(): string
    {
        return 'Nadkomplet na warsztacie — mail do klientki '.$this->booking->order->number;
    }

    public function envelope(): Envelope
    {
        $contact = app(Settings::class)->get('contact_email');

        return new Envelope(
            subject: 'Termin warsztatu się zapełnił · '.$this->booking->order->number,
            replyTo: $contact ? [new Address($contact, __('checkout::mail.from'))] : [],
        );
    }

    public function content(): Content
    {
        $settings = app(Settings::class);

        return new Content(
            view: 'workshops::mail.booking-overbooked',
            text: 'workshops::mail.booking-overbooked-text',
            with: [
                'order' => $this->booking->order,
                'date' => $this->booking->workshopDate,
                'contactEmail' => $settings->get('contact_email'),
                'contactPhone' => $settings->get('contact_phone'),
                'city' => $settings->get('footer_city'),
            ],
        );
    }
}
