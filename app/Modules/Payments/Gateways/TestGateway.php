<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Checkout\Actions\SimulatePayment;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Support\StartedPayment;

/**
 * Stands in for Stripe while the shop has no keys, so the whole purchase path can be walked on a
 * laptop and on staging. It never runs in production — SimulatePayment throws there.
 */
class TestGateway implements PaymentGateway
{
    public function __construct(private SimulatePayment $simulate) {}

    public function start(Order $order, ?string $blikCode): StartedPayment
    {
        if (($this->simulate)($order, $blikCode)) {
            return StartedPayment::done();
        }

        return StartedPayment::rejected(__('payments::gateway.code_refused'));
    }

    public function state(Order $order): PaymentState
    {
        return match ($order->payment_status) {
            PaymentStatus::Paid => PaymentState::Succeeded,
            PaymentStatus::Failed => PaymentState::Failed,
            default => PaymentState::Pending,
        };
    }
}
