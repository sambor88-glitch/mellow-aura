<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Checkout\Actions\AttachPaymentReference;
use App\Modules\Checkout\Actions\SimulatePayment;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Events\OrderPlaced;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Support\StartedPayment;
use Illuminate\Support\Str;

/**
 * Stands in for Przelewy24 without its keys, so the whole purchase path can be walked on a laptop and on staging.
 * It never runs in production — SimulatePayment throws there. Its BLIK codes are in SimulatePayment.
 */
class TestGateway implements PaymentGateway
{
    public function __construct(private SimulatePayment $simulate, private AttachPaymentReference $attachReference) {}

    public function start(Order $order, ?string $blikCode): StartedPayment
    {
        if ($order->payment_method === PaymentMethod::Blik && $blikCode === SimulatePayment::UNCONFIRMED_BLIK_CODE) {
            OrderPlaced::dispatch($order);

            return $this->waitForBank($order);
        }

        if (($this->simulate)($order, $blikCode)) {
            return StartedPayment::done();
        }

        return StartedPayment::rejected(__('payments::gateway.code_refused'));
    }

    public function chargeBlik(Order $order, string $blikCode): StartedPayment
    {
        if ($blikCode === SimulatePayment::UNCONFIRMED_BLIK_CODE) {
            return $this->waitForBank($order);
        }

        if (($this->simulate)($order, $blikCode, announce: false)) {
            return StartedPayment::done();
        }

        return StartedPayment::rejected(__('payments::gateway.code_refused'));
    }

    public function takesBlikCode(): bool
    {
        return true;
    }

    public function state(Order $order): PaymentState
    {
        return match ($order->payment_status) {
            PaymentStatus::Paid => PaymentState::Succeeded,
            PaymentStatus::Failed => PaymentState::Failed,
            default => PaymentState::Pending,
        };
    }

    /** A code nobody confirms: the order waits, so the BLIK page and its clock can be seen on staging. */
    private function waitForBank(Order $order): StartedPayment
    {
        ($this->attachReference)($order, 'test-blik-'.Str::lower(Str::random(12)));

        return StartedPayment::awaitingBank();
    }
}
