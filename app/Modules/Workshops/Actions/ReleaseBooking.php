<?php

namespace App\Modules\Workshops\Actions;

use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Actions\RestoreVoucherBalance;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Support\Facades\DB;

/**
 * Gives the held seats back when the payment failed and the customer is back at the form, so they do not wait
 * for the hold to run out. A payment that was never finished frees the seats on its own: a held booking counts only
 * until its expires_at. A confirmed booking is never released here.
 * Voucher money the unpaid order held goes back to the voucher with the seats (MA-244); without a release, the hold
 * runs out on its own (Gifts\Actions\ReleaseVoucherHolds).
 */
class ReleaseBooking
{
    public function __construct(private RestoreVoucherBalance $restoreVoucherBalance) {}

    public function __invoke(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $booking = WorkshopBooking::query()->where('order_id', $order->id)->first();

            if ($booking === null || $booking->status !== BookingStatus::Held) {
                return;
            }

            // The same lock as PlaceBooking and ConfirmBooking, so a payment confirmed right now is never undone.
            WorkshopDate::query()->lockForUpdate()->findOrFail($booking->workshop_date_id);

            if ($booking->fresh()?->status === BookingStatus::Held) {
                $booking->update(['status' => BookingStatus::Released]);
                $this->restoreVoucherBalance->abandoned($order);
            }
        });
    }
}
