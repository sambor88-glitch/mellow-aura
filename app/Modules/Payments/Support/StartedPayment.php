<?php

namespace App\Modules\Payments\Support;

/**
 * What the checkout has to do next, right after the payment was started.
 */
final class StartedPayment
{
    private function __construct(
        public readonly ?string $error = null,
        public readonly ?string $redirectTo = null,
        public readonly bool $awaitsBank = false,
    ) {}

    /**
     * Nothing left for the browser: the stand-in gateway already paid, or the customer pays by bank transfer
     * and the order waits for the money. The order's payment status tells which.
     */
    public static function done(): self
    {
        return new self;
    }

    /**
     * The BLIK code was taken and the customer now confirms the payment in the bank's app. The checkout waits
     * on its own page, with a clock, until the notification says the money is in (checkout.blik).
     */
    public static function awaitingBank(): self
    {
        return new self(awaitsBank: true);
    }

    /**
     * The customer finishes on the gateway's own page (Przelewy24's bank and card page) and comes back to
     * the confirmation.
     */
    public static function redirect(string $url): self
    {
        return new self(redirectTo: $url);
    }

    /**
     * Refused before it started, with a sentence for the customer.
     */
    public static function rejected(string $message): self
    {
        return new self(error: $message);
    }
}
