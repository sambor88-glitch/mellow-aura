<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stands in for the payment gateway until Przelewy24 arrives (MA-50). The BLIK code 000000
 * plays a payment the bank rejects, so the failure path can be tried on staging.
 */
class SimulatePayment
{
    public const REJECTED_BLIK_CODE = '000000';

    public function __construct(private MarkOrderPaid $markOrderPaid) {}

    public function __invoke(Order $order, ?string $blikCode): bool
    {
        throw_if(app()->isProduction(), RuntimeException::class, 'Simulated payments never run in production.');

        if ($order->payment_method === PaymentMethod::Blik && $blikCode === self::REJECTED_BLIK_CODE) {
            $order->update(['payment_status' => PaymentStatus::Failed]);

            return false;
        }

        ($this->markOrderPaid)($order, 'test-'.Str::lower(Str::random(12)));

        return true;
    }
}
