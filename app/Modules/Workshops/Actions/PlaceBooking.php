<?php

namespace App\Modules\Workshops\Actions;

use App\Modules\Checkout\Actions\PlaceOrder;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\WorkshopLine;
use Illuminate\Support\Facades\DB;

/**
 * Holds the seats and places the order for them in one transaction, with the date locked: two people asking for
 * the last seat at once are counted one after the other, and the second one hears that it is gone.
 * The seats wait WorkshopBooking::HOLD_MINUTES for the payment; ConfirmBooking makes them hers.
 */
class PlaceBooking
{
    public function __construct(private PlaceOrder $placeOrder) {}

    /**
     * @param  array{name: string, price_gross: int}  $workshop  the date's row of WorkshopTypes::all()
     * @param  array<string, mixed>  $data  validated booking fields, in the shape PlaceOrder takes
     * @return ?WorkshopBooking null when fewer seats are free than asked for
     */
    public function __invoke(WorkshopDate $date, array $workshop, int $seats, array $data): ?WorkshopBooking
    {
        return DB::transaction(function () use ($date, $workshop, $seats, $data) {
            $date = WorkshopDate::query()->lockForUpdate()->findOrFail($date->id);

            if ($date->starts_at->isPast() || $date->seatsLeft() < $seats) {
                return null;
            }

            $line = new WorkshopLine($date, $workshop, $seats);
            $order = ($this->placeOrder)(collect([$line->key => $line]), [...$data, 'shipping_method' => ShippingMethods::EMAIL], 0);

            return WorkshopBooking::create([
                'workshop_date_id' => $date->id,
                'order_id' => $order->id,
                'seats' => $seats,
                'status' => BookingStatus::Held,
                'expires_at' => now()->addMinutes(WorkshopBooking::HOLD_MINUTES),
            ]);
        });
    }
}
