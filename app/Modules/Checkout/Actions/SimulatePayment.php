<?php

namespace App\Modules\Checkout\Actions;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Events\OrderPlaced;
use App\Modules\Checkout\Models\Order;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stands in for Przelewy24 until its gateway is connected (Payments\Gateways\TestGateway, MA-167).
 * The BLIK code 000000 plays a payment the bank rejects, so the failure path can be tried on staging,
 * and 111111 one nobody confirms in the bank's app, so the clock on the BLIK page runs out (TestGateway).
 */
class SimulatePayment
{
    public const REJECTED_BLIK_CODE = '000000';

    public const UNCONFIRMED_BLIK_CODE = '111111';

    public function __construct(private MarkOrderPaid $markOrderPaid) {}

    /**
     * $announce is false for a second BLIK code on the same order: the customer has the order summary already.
     */
    public function __invoke(Order $order, ?string $blikCode, bool $announce = true): bool
    {
        throw_if(app()->isProduction(), RuntimeException::class, 'Simulated payments never run in production.');

        if ($order->payment_method === PaymentMethod::Blik && $blikCode === self::REJECTED_BLIK_CODE) {
            $order->update(['payment_status' => PaymentStatus::Failed]);

            return false;
        }

        // A payment that starts gets the order summary first and the confirmation after, like with the gateway.
        if ($announce) {
            OrderPlaced::dispatch($order);
        }

        ($this->markOrderPaid)($order, 'test-'.Str::lower(Str::random(12)));

        return true;
    }
}
