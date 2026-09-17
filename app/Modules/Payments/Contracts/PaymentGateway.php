<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Support\StartedPayment;

/**
 * One payment, from „Płacę” to the answer from the bank.
 *
 * Which gateway answers depends on the keys: with them Stripe, without them the stand-in that lets
 * the whole path be walked on a laptop and on staging. Nothing outside this module knows which one it is.
 */
interface PaymentGateway
{
    /**
     * Starts the payment for an order that has just been saved. The BLIK code never reaches our
     * database — it goes from the form to the gateway and nowhere else.
     */
    public function start(Order $order, ?string $blikCode): StartedPayment;

    /**
     * What the provider says about a payment. Asked when the customer is back in the shop and the
     * webhook has not arrived yet; the provider decides, never the browser.
     */
    public function state(Order $order): PaymentState;
}
