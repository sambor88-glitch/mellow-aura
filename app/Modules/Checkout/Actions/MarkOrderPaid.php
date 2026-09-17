<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Catalog\Actions\DecrementStock;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Events\OrderPaid;
use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Actions\IssueVouchers;
use Illuminate\Support\Facades\DB;

/**
 * Marks an order as paid, takes its pieces off the shelf and issues its vouchers, in one transaction
 * and exactly once, however many times the confirmation arrives. Stripe's webhook calls it as well.
 * A piece that someone else bought first stays on the order as missing, and the order is still paid.
 */
class MarkOrderPaid
{
    public function __construct(private DecrementStock $decrementStock, private IssueVouchers $issueVouchers) {}

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

            $order->update([
                // Vouchers sent as PDFs alone are delivered by the e-mail that goes out now, so nothing is left to do.
                'status' => $order->sendsParcel() ? OrderStatus::InProgress : OrderStatus::Completed,
                'completed_at' => $order->sendsParcel() ? null : now(),
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
