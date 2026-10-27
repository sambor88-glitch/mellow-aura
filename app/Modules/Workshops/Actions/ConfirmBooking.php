<?php

namespace App\Modules\Workshops\Actions;

use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Support\Facades\DB;

/**
 * Makes the seats hers once the payment is confirmed. Checkout\Actions\MarkOrderPaid calls it inside its own
 * transaction, so the seats and the payment are settled together. The date is locked while the seats are counted.
 *
 * A payment after the hold ran out still gets the seats when nobody took them meanwhile. When someone did, the
 * booking is overbooked: the money came in for a seat that is gone, and Kasia hears about it to return it.
 * A booking Kasia cancelled before the payment came stays cancelled: she hears about the money to return.
 */
class ConfirmBooking
{
    public function __construct(private Alerts $alerts) {}

    /**
     * @return ?BookingStatus null for an order that books no workshop
     */
    public function __invoke(Order $order): ?BookingStatus
    {
        return DB::transaction(function () use ($order) {
            $booking = WorkshopBooking::query()->where('order_id', $order->id)->first();

            if ($booking === null || in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::Overbooked], true)) {
                return $booking?->status;
            }

            if ($booking->status === BookingStatus::Cancelled) {
                DB::afterCommit(fn () => $this->alerts->send(
                    'workshop-cancelled-paid:'.$order->number,
                    'Płatność za anulowany zapis — do zwrotu',
                    $order->number.' ('.$order->name.', '.$order->email.'): przyszła płatność za zapis na warsztat, który anulowałaś w panelu. '
                        .'Zwróć wpłatę '.$order->money($order->total_gross).'.',
                ));

                return BookingStatus::Cancelled;
            }

            $date = WorkshopDate::query()->lockForUpdate()->findOrFail($booking->workshop_date_id);
            $takenByOthers = (int) $date->bookings()->takingSeats()->whereKeyNot($booking->id)->sum('seats');

            if ($date->seats - $takenByOthers >= $booking->seats) {
                $booking->update(['status' => BookingStatus::Confirmed, 'confirmed_at' => now()]);

                return BookingStatus::Confirmed;
            }

            $booking->update(['status' => BookingStatus::Overbooked]);

            DB::afterCommit(fn () => $this->alerts->send(
                'workshop-overbooked:'.$order->number,
                'Nadkomplet na warsztacie — do zwrotu',
                $order->number.' ('.$order->name.', '.$order->email.'): płatność za warsztat '.$date->dayLabel().', '.$date->timeLabel()
                    .' (miejsca: '.$booking->seats.') przyszła po czasie trzymania miejsca, a termin jest już pełny. '
                    .'Zwróć wpłatę '.$order->money($order->total_gross).' albo zaproponuj inny termin.',
            ));

            return BookingStatus::Overbooked;
        });
    }
}
