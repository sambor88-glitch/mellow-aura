<?php

namespace App\Modules\Workshops\Actions;

use App\Modules\Checkout\Actions\ChangeOrderStatus;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Gifts\Actions\RestoreVoucherBalance;
use App\Modules\Gifts\Actions\ReturnVoucher;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Mail\BookingCancelled;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Kasia cancels a booking from the panel. The seats leave WorkshopDate::seatsLeft() at once and the reminder never
 * goes out. The money goes back by hand — the gateway has no refunds — so a paid booking puts its order under
 * „Problem” with the amount to return; after the refund Kasia marks the order done. The date is locked as in
 * ConfirmBooking, so a payment confirmed at the same moment is counted first or not at all.
 * A booking paid with a voucher has no money to return: the voucher is valid again instead (MA-137).
 * What an amount voucher paid goes back to its balance at once (MA-244); only the rest is money to return, and a booking
 * the vouchers paid in full has none. An unpaid booking gives back what its order holds.
 */
class CancelBooking
{
    public function __construct(private ChangeOrderStatus $changeOrderStatus, private ReturnVoucher $returnVoucher, private RestoreVoucherBalance $restoreVoucherBalance) {}

    /**
     * @param  bool  $notify  send her the cancellation mail; only a confirmed booking gets one, since an overbooked one
     *                        already heard about the refund and an unpaid one has nothing to return
     * @return bool false when the booking was already out of the count
     */
    public function __invoke(WorkshopBooking $booking, bool $notify = true): bool
    {
        return DB::transaction(function () use ($booking, $notify) {
            // A booking without its date was cancelled before the date was deleted.
            if ($booking->workshop_date_id === null) {
                return false;
            }

            WorkshopDate::query()->lockForUpdate()->findOrFail($booking->workshop_date_id);
            $booking = WorkshopBooking::query()->with(['order', 'workshopDate'])->findOrFail($booking->id);
            $was = $booking->status;

            if (in_array($was, [BookingStatus::Released, BookingStatus::Cancelled], true)) {
                return false;
            }

            $booking->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);

            $order = $booking->order;

            if ($booking->voucher_id !== null) {
                ($this->returnVoucher)($booking->voucher_id);
            } elseif ($was->isPaid()) {
                $restored = ($this->restoreVoucherBalance)($order);

                if ($order->amountDue() > 0) {
                    ($this->changeOrderStatus)($order, OrderStatus::Problem, problemNote: 'Zapis na warsztat anulowany w panelu. Zwróć wpłatę '
                        .$order->money($order->amountDue()).', potem zmień status na „Zakończone”.'
                        .($restored > 0 ? ' '.$order->money($restored).' z vouchera wróciło już na jego saldo.' : ''));
                }
            } else {
                $this->restoreVoucherBalance->abandoned($order);
            }

            if ($notify && $was === BookingStatus::Confirmed) {
                DB::afterCommit(fn () => rescue(fn () => Mail::to(new Address($booking->order->email, $booking->order->name))
                    ->send(new BookingCancelled($booking))));
            }

            return true;
        });
    }
}
