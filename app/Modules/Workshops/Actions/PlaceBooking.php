<?php

namespace App\Modules\Workshops\Actions;

use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\PlaceOrder;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Gifts\Actions\RedeemVoucher;
use App\Modules\Gifts\Exceptions\BalanceNotAccepted;
use App\Modules\Gifts\Exceptions\VoucherNotAccepted;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use App\Modules\Workshops\Support\WorkshopLine;
use Illuminate\Support\Facades\DB;

/**
 * Holds the seats and places the order for them in one transaction, with the date locked: two people asking for
 * the last seat at once are counted one after the other, and the second one hears that it is gone.
 * The seats wait WorkshopBooking::HOLD_MINUTES for the payment; ConfirmBooking makes them hers.
 *
 * With a voucher code (MA-137) there is nothing to wait for: the voucher is locked and checked, the seats it books are
 * counted, and the voucher is used and the booking confirmed in the same transaction. Its order costs nothing — the
 * money came in when the voucher was bought — and goes through MarkOrderPaid like a paid one, so the seats, the mails,
 * the reminder and the panel treat it the same.
 *
 * Amount vouchers (MA-244) pay through PlaceOrder: their money is held in the same transaction as the seats, after
 * the date's lock, so the date always comes before the vouchers. When they pay for everything, the booking is paid and
 * confirmed at once as well; otherwise the rest goes through the gateway like any booking.
 */
class PlaceBooking
{
    public function __construct(private PlaceOrder $placeOrder, private RedeemVoucher $redeemVoucher, private MarkOrderPaid $markOrderPaid) {}

    /**
     * @param  array{name: string, price_gross: int}  $workshop  the date's row of WorkshopTypes::all()
     * @param  array<string, mixed>  $data  validated booking fields, in the shape PlaceOrder takes
     * @param  list<string>  $voucherCodes  amount vouchers to pay with, in the order typed
     * @return ?WorkshopBooking null when fewer seats are free than asked for
     *
     * @throws BalanceNotAccepted
     */
    public function __invoke(WorkshopDate $date, array $workshop, int $seats, array $data, array $voucherCodes = []): ?WorkshopBooking
    {
        return DB::transaction(function () use ($date, $workshop, $seats, $data, $voucherCodes) {
            $date = WorkshopDate::query()->lockForUpdate()->findOrFail($date->id);

            if ($date->starts_at->isPast() || $date->seatsLeft() < $seats) {
                return null;
            }

            $booking = $this->hold($date, $workshop, $seats, $data, $voucherCodes);

            if ($booking->order->vouchers_gross > 0 && $booking->order->amountDue() === 0) {
                ($this->markOrderPaid)($booking->order, 'voucher');

                return $booking->fresh();
            }

            return $booking;
        });
    }

    /**
     * @param  array{name: string, price_gross: int}  $workshop  the date's row of WorkshopTypes::all()
     * @param  array<string, mixed>  $data  validated booking fields, in the shape PlaceOrder takes
     * @return ?WorkshopBooking the confirmed booking, or null when fewer seats are free than the voucher books
     *
     * @throws VoucherNotAccepted
     */
    public function withVoucher(WorkshopDate $date, array $workshop, string $code, array $data): ?WorkshopBooking
    {
        return DB::transaction(function () use ($date, $workshop, $code, $data) {
            // The date first, then the voucher, the same order as CancelBooking.
            $date = WorkshopDate::query()->lockForUpdate()->findOrFail($date->id);

            if ($date->starts_at->isPast()) {
                return null;
            }

            $voucher = $this->redeemVoucher->lockFor($code, $date->workshop_code, $date->starts_at);

            if ($date->seatsLeft() < $voucher->seats) {
                return null;
            }

            $booking = $this->hold($date, [...$workshop, 'price_gross' => 0], $voucher->seats, [...$data, 'payment_method' => PaymentMethod::Voucher->value]);
            $booking->update(['voucher_id' => $voucher->id]);
            ($this->redeemVoucher)($voucher);
            ($this->markOrderPaid)($booking->order, $voucher->code);

            return $booking->fresh();
        });
    }

    /**
     * @param  array{name: string, price_gross: int}  $workshop
     * @param  array<string, mixed>  $data
     * @param  list<string>  $voucherCodes
     */
    private function hold(WorkshopDate $date, array $workshop, int $seats, array $data, array $voucherCodes = []): WorkshopBooking
    {
        $line = new WorkshopLine($date, $workshop, $seats);
        $order = ($this->placeOrder)(collect([$line->key => $line]), [...$data, 'shipping_method' => ShippingMethods::EMAIL], 0, $voucherCodes);

        return WorkshopBooking::create([
            'workshop_date_id' => $date->id,
            'order_id' => $order->id,
            'seats' => $seats,
            'status' => BookingStatus::Held,
            'expires_at' => now()->addMinutes(WorkshopBooking::HOLD_MINUTES),
        ]);
    }
}
