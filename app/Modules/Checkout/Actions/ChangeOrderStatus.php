<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Events\OrderShipped;
use App\Modules\Checkout\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Moves a paid order on from the panel: sent (the customer hears about it, with the tracking number if there is one),
 * done, a problem to sort out, or back to the studio after a mistake. Each date stays with the status it belongs to.
 *
 * The customer hears „sent” once: only the first time the order gets a `shipped_at`, as in the ShipX sync. Going back
 * to the studio keeps the date and the tracking number, and an empty tracking field never wipes a number already saved.
 */
class ChangeOrderStatus
{
    public function __invoke(Order $order, OrderStatus $status, ?string $trackingNumber = null, ?string $problemNote = null): Order
    {
        return DB::transaction(function () use ($order, $status, $trackingNumber, $problemNote) {
            // Two taps arrive as two requests: the second one waits here and sees the first one's `shipped_at`.
            $order->setRawAttributes(Order::query()->lockForUpdate()->findOrFail($order->getKey())->getAttributes(), true);
            $firstShipment = $order->shipped_at === null;

            $order->fill(['status' => $status]);

            match ($status) {
                OrderStatus::Shipped => $order->fill([
                    'tracking_number' => $trackingNumber ?? $order->tracking_number,
                    'shipped_at' => $order->shipped_at ?? now(),
                    'completed_at' => null,
                    'problem_note' => null,
                ]),
                OrderStatus::Completed => $order->fill(['completed_at' => now(), 'problem_note' => null]),
                OrderStatus::Problem => $order->fill(['problem_note' => $problemNote, 'completed_at' => null]),
                default => $order->fill(['completed_at' => null, 'problem_note' => null]),
            };

            $order->save();

            if ($status === OrderStatus::Shipped && $firstShipment) {
                OrderShipped::dispatch($order);
            }

            return $order;
        });
    }
}
