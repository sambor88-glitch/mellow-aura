<?php

namespace App\Modules\Workshops\Listeners;

use App\Modules\Admin\Support\Push\PanelNotice;
use App\Modules\Admin\Support\Push\PanelPush;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Models\WorkshopBooking;

/**
 * Rings Kasia's phone about a paid workshop booking (MA-345): the day, the seats and the amount, never the name.
 * An overbooked one is left to the alert from ConfirmBooking. Opens the list of people on that date.
 */
class NotifyPanelAboutBooking
{
    public function __construct(private PanelPush $push) {}

    public function handle(OrderPaid $event): void
    {
        $booking = WorkshopBooking::query()->with(['order', 'workshopDate'])->where('order_id', $event->order->id)->first();

        if ($booking === null || $booking->status !== BookingStatus::Confirmed || $booking->workshopDate === null) {
            return;
        }

        $date = $booking->workshopDate;
        $seats = $booking->seats === 1 ? '1 osoba' : $booking->seats.(in_array($booking->seats % 10, [2, 3, 4], true) && ! in_array($booking->seats % 100, [12, 13, 14], true) ? ' osoby' : ' osób');

        rescue(fn () => $this->push->notify(new PanelNotice(
            title: 'Nowy zapis na warsztat · '.$booking->order->money($booking->order->total_gross),
            body: $date->dayLabel().', '.$date->timeLabel().' · '.$seats,
            url: route('admin.workshops.dates.bookings', $date),
            route: 'admin.workshops.dates.bookings',
            tag: 'booking-'.$booking->id,
        )));
    }
}
