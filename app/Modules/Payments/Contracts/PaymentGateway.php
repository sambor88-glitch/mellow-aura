<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Support\StartedPayment;

/**
 * One payment, from „Płacę” to the answer from the bank.
 *
 * Przelewy24 takes the payments (MA-167); until then the stand-in lets the whole path be walked on a
 * laptop and on staging. Nothing outside this module knows which one it is.
 */
interface PaymentGateway
{
    /**
     * Starts the payment for an order that has just been saved. The BLIK code never reaches our
     * database — it goes from the form to the gateway and nowhere else.
     */
    public function start(Order $order, ?string $blikCode): StartedPayment;

    /**
     * A new BLIK code for an order that is still unpaid: the first one ran out or the bank refused it.
     * The same order and the same cart, only a new try at the payment.
     */
    public function chargeBlik(Order $order, string $blikCode): StartedPayment;

    /**
     * Whether the checkout asks for the BLIK code on its own page (BLIK Level 0). Without it the customer
     * picks BLIK and types the code on the gateway's page.
     */
    public function takesBlikCode(): bool;

    /**
     * What the provider says about a payment. Asked when the customer is back in the shop and the
     * webhook has not arrived yet; the provider decides, never the browser.
     */
    public function state(Order $order): PaymentState;
}
