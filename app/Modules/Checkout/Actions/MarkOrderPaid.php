<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Catalog\Actions\DecrementStock;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Actions\IssueVouchers;
use App\Modules\Workshops\Actions\ConfirmBooking;
use App\Modules\Workshops\Enums\BookingStatus;
use Illuminate\Support\Facades\DB;

/**
 * Marks an order as paid, takes its pieces off the shelf and issues its vouchers, in one transaction
 * and exactly once, however many times the confirmation arrives. The gateway's webhook calls it as well.
 * A piece that someone else bought first stays on the order as missing, and the order is still paid.
 * Seats on a workshop are confirmed here too; seats someone else took while the payment was late put the order
 * under „Problem”, for Kasia to return the money.
 */
class MarkOrderPaid
{
    public function __construct(private DecrementStock $decrementStock, private IssueVouchers $issueVouchers, private ConfirmBooking $confirmBooking) {}

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

            $overbooked = ($this->confirmBooking)($order) === BookingStatus::Overbooked;

            $order->update([
                // Vouchers sent as PDFs alone are delivered by the e-mail that goes out now, so nothing is left to do.
                'status' => match (true) {
                    $overbooked => OrderStatus::Problem,
                    $order->sendsParcel() => OrderStatus::InProgress,
                    default => OrderStatus::Completed,
                },
                'completed_at' => $order->sendsParcel() || $overbooked ? null : now(),
                'problem_note' => $overbooked ? 'Nadkomplet: płatność przyszła po czasie trzymania miejsca, a termin jest pełny. Zwróć wpłatę albo zaproponuj inny termin.' : $order->problem_note,
                'payment_status' => PaymentStatus::Paid,
                'payment_provider_id' => $providerId,
                'paid_at' => now(),
            ]);

            ($this->issueVouchers)($order);

            OrderPaid::dispatch($order);

            return $order;
        });
    }
}
