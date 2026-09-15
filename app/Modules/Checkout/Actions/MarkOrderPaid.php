<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Catalog\Actions\DecrementStock;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Marks an order as paid and takes its pieces off the shelf, in one transaction and exactly once,
 * however many times the confirmation arrives. The payment webhook will call it too (MA-51).
 * A piece that someone else bought first stays on the order as missing, and the order is still paid.
 */
class MarkOrderPaid
{
    public function __construct(private DecrementStock $decrementStock) {}

    public function __invoke(Order $order, string $providerId): Order
    {
        return DB::transaction(function () use ($order, $providerId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->payment_status === PaymentStatus::Paid) {
                return $order;
            }

            // Variants in id order, so two payments that share one always lock rows in the same order.
            foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
                $missing = $item->product_variant_id === null
                    ? $item->quantity
                    : ($this->decrementStock)($item->product_variant_id, $item->quantity);

                if ($missing > 0) {
                    $item->update(['missing_quantity' => $missing]);
                }
            }

            $order->update([
                'status' => OrderStatus::InProgress,
                'payment_status' => PaymentStatus::Paid,
                'payment_provider_id' => $providerId,
                'paid_at' => now(),
            ]);

            return $order;
        });
    }
}
