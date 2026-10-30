<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Catalog\Actions\DecrementStock;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Checkout\Models\Order;
use App\Modules\CustomOrders\Actions\ConfirmPayment;
use App\Modules\Gifts\Actions\CaptureVoucherBalance;
use App\Modules\Gifts\Actions\IssueGiftChoices;
use App\Modules\Gifts\Actions\IssueVouchers;
use App\Modules\Workshops\Actions\ConfirmBooking;
use App\Modules\Workshops\Enums\BookingStatus;
use Illuminate\Support\Facades\DB;

/**
 * Marks an order as paid, takes its pieces off the shelf and issues its vouchers, in one transaction
 * and exactly once, however many times the confirmation arrives. The gateway's webhook calls it as well.
 * A piece that someone else bought first stays on the order as missing, and the order is still paid.
 * Seats on a workshop are confirmed here too; seats someone else took while the payment was late put the order
 * under „Problem”, for Kasia to return the money. A payment for a custom order counts towards its idea here as well;
 * money for a declined idea or above the quote also puts the order under „Problem”.
 * Voucher money held for the order is spent here too (MA-211); a voucher emptied meanwhile puts it under „Problem”.
 * Gift choice cards get their codes here as well (MA-235).
 */
class MarkOrderPaid
{
    public function __construct(private DecrementStock $decrementStock, private IssueVouchers $issueVouchers, private ConfirmBooking $confirmBooking, private ConfirmPayment $confirmCustomOrderPayment, private CaptureVoucherBalance $captureVoucherBalance, private IssueGiftChoices $issueGiftChoices) {}

    public function __invoke(Order $order, string $providerId): Order
    {
        return DB::transaction(function () use ($order, $providerId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->payment_status === PaymentStatus::Paid) {
                return $order;
            }

            // Variants in id order, so two payments that share one always lock rows in the same order.
            foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
                $missing = match (true) {
                    $item->is_made_to_order => 0,
                    // The size was removed from the shop after the order was placed.
                    $item->product_variant_id === null => $item->quantity,
                    default => ($this->decrementStock)($item->product_variant_id, $item->quantity),
                };

                if ($missing > 0) {
                    $item->update(['missing_quantity' => $missing]);
                }
            }

            // The workshop date before the vouchers: PlaceBooking and CancelBooking lock in that order too.
            $booking = ($this->confirmBooking)($order);
            $voucherNote = ($this->captureVoucherBalance)($order);
            // Only the money that came in goes back to the account; the voucher part goes back to the voucher (MA-244).
            $refund = 'Zwróć wpłatę '.$order->money($order->amountDue())
                .($order->vouchers_gross > 0 ? ' i oddaj '.$order->money($order->vouchers_gross).' na voucher' : '');
            $problemNote = match ($booking) {
                BookingStatus::Overbooked => 'Nadkomplet: płatność przyszła po czasie trzymania miejsca, a termin jest pełny. '.$refund.' albo zaproponuj inny termin.',
                BookingStatus::Cancelled => 'Płatność za zapis na warsztat, który anulowałaś w panelu. '.$refund.'.',
                default => ($this->confirmCustomOrderPayment)($order),
            };
            $problemNote = collect([$problemNote, $voucherNote])->filter()->join(' ') ?: null;
            $problem = $problemNote !== null;

            $order->update([
                // Vouchers sent as PDFs alone are delivered by the e-mail that goes out now, so nothing is left to do.
                'status' => match (true) {
                    $problem => OrderStatus::Problem,
                    $order->sendsParcel() => OrderStatus::InProgress,
                    default => OrderStatus::Completed,
                },
                'completed_at' => $order->sendsParcel() || $problem ? null : now(),
                'problem_note' => $problemNote ?? $order->problem_note,
                'payment_status' => PaymentStatus::Paid,
                'payment_provider_id' => $providerId,
                'paid_at' => now(),
            ]);

            ($this->issueVouchers)($order);
            ($this->issueGiftChoices)($order);

            OrderPaid::dispatch($order);

            return $order;
        });
    }
}
