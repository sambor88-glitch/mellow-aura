<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Events\OrderShipped;
use App\Modules\Checkout\Models\Order;

/**
 * Moves a paid order on from the panel: sent (the customer hears about it, with the tracking number if there is one),
 * done, a problem to sort out, or back to the studio after a mistake. Each date stays with the status it belongs to.
 */
class ChangeOrderStatus
{
    public function __invoke(Order $order, OrderStatus $status, ?string $trackingNumber = null, ?string $problemNote = null): Order
    {
        $order->fill(['status' => $status]);

        match ($status) {
            OrderStatus::Shipped => $order->fill(['tracking_number' => $trackingNumber, 'shipped_at' => now(), 'completed_at' => null, 'problem_note' => null]),
            OrderStatus::Completed => $order->fill(['completed_at' => now(), 'problem_note' => null]),
            OrderStatus::Problem => $order->fill(['problem_note' => $problemNote, 'completed_at' => null]),
            default => $order->fill(['tracking_number' => null, 'shipped_at' => null, 'completed_at' => null, 'problem_note' => null]),
        };

        $order->save();

        if ($status === OrderStatus::Shipped) {
            OrderShipped::dispatch($order);
        }

        return $order;
    }
}
