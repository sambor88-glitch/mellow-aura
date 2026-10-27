<?php

namespace App\Modules\Workshops\Mail;

use App\Modules\Shared\Mail\QueuedMail;
use App\Modules\Workshops\Models\WorkshopBooking;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells Kasia someone booked and paid: who, which date, how many seats, and how many are left.
 * It takes the place of the shop's notice about a paid order. Replying writes straight to the customer.
 */
class NewBookingReceived extends QueuedMail
{
    public function __construct(public WorkshopBooking $booking) {}

    public function description(): string
    {
        return 'Powiadomienie dla Ciebie o zapisie na warsztat '.$this->booking->order->number;
    }

    public function envelope(): Envelope
    {
        $order = $this->booking->order;
        $date = $this->booking->workshopDate;

        return new Envelope(
            subject: 'Nowy zapis na warsztat · '.$date->dayLabel().', '.$date->starts_at->format('H:i').' · '.$this->booking->seats.' os.',
            replyTo: [new Address($order->email, $order->name)],
        );
    }

    public function content(): Content
    {
        $order = $this->booking->order->loadMissing('items');

        return new Content(
            view: 'workshops::mail.new-booking',
            text: 'workshops::mail.new-booking-text',
            with: [
                'booking' => $this->booking,
                'order' => $order,
                'date' => $this->booking->workshopDate,
                'workshop' => (string) $order->items->sortBy('id')->first()?->product_name,
            ],
        );
    }
}
