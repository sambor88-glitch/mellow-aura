<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentState;

/**
 * The customer can be back in the shop before the gateway's own call reaches us, so we ask it ourselves —
 * on the confirmation and every few seconds on the BLIK page. MarkOrderPaid is safe to call twice, so the
 * call arriving later changes nothing.
 */
class SettlePayment
{
    public function __construct(private PaymentGateway $gateway, private MarkOrderPaid $markOrderPaid) {}

    public function __invoke(Order $order): Order
    {
        if ($order->payment_status !== PaymentStatus::Pending || blank($order->payment_provider_id)) {
            return $order;
        }

        return match ($this->gateway->state($order)) {
            PaymentState::Succeeded => ($this->markOrderPaid)($order, $order->payment_provider_id),
            PaymentState::Failed => tap($order)->update(['payment_status' => PaymentStatus::Failed]),
            PaymentState::Pending => $order,
        };
    }
}
