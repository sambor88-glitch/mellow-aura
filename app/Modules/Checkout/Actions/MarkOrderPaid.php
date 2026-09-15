<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Marks an order as paid exactly once, however many times the confirmation arrives. The payment
 * webhook will call it as well (MA-51), and stock will come off in this same transaction (MA-52).
 */
class MarkOrderPaid
{
    public function __invoke(Order $order, string $providerId): Order
    {
        return DB::transaction(function () use ($order, $providerId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->payment_status !== PaymentStatus::Paid) {
                $order->update([
                    'status' => OrderStatus::InProgress,
                    'payment_status' => PaymentStatus::Paid,
                    'payment_provider_id' => $providerId,
                    'paid_at' => now(),
                ]);
            }

            return $order;
        });
    }
}
