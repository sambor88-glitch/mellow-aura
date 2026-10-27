<?php

namespace App\Modules\Workshops\Listeners;

use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Settings\Settings;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Mail\BookingConfirmed;
use App\Modules\Workshops\Mail\BookingOverbooked;
use App\Modules\Workshops\Mail\NewBookingReceived;
use App\Modules\Workshops\Models\WorkshopBooking;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * For a paid booking, the workshop mails in place of the shop's (Checkout\Listeners\SendOrderEmails skips such an
 * order): the confirmation with the address and Kasia's notice, or for an overbooked one only the apology — Kasia
 * already has the alert from ConfirmBooking. A mail that cannot be queued is reported and never undoes the payment.
 */
class SendBookingEmails
{
    public function __construct(private Settings $settings) {}

    public function handle(OrderPaid $event): void
    {
        $booking = WorkshopBooking::query()->with(['order', 'workshopDate'])->where('order_id', $event->order->id)->first();

        if ($booking === null) {
            return;
        }

        $customer = new Address($booking->order->email, $booking->order->name);

        if ($booking->status === BookingStatus::Overbooked) {
            rescue(fn () => Mail::to($customer)->send(new BookingOverbooked($booking)));

            return;
        }

        if ($booking->status !== BookingStatus::Confirmed) {
            return;
        }

        rescue(fn () => Mail::to($customer)->send(new BookingConfirmed($booking)));

        if ($owner = $this->settings->get('contact_email')) {
            rescue(fn () => Mail::to($owner)->send(new NewBookingReceived($booking)));
        }
    }
}
