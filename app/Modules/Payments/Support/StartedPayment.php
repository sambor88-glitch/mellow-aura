<?php

namespace App\Modules\Payments\Support;

/**
 * What the checkout has to do next, right after the payment was started.
 */
final class StartedPayment
{
    private function __construct(
        public readonly ?string $clientSecret = null,
        public readonly ?string $error = null,
    ) {}

    /**
     * Nothing left for the browser: the stand-in gateway already paid, or the customer pays by
     * bank transfer and the order waits for the money. The order's payment status tells which.
     */
    public static function done(): self
    {
        return new self;
    }

    /**
     * The browser hands the BLIK code or the card to the gateway with this secret, so the card
     * number and the code never pass through our server.
     */
    public static function confirmInBrowser(string $clientSecret): self
    {
        return new self(clientSecret: $clientSecret);
    }

    /**
     * Refused before it started, with a sentence for the customer.
     */
    public static function rejected(string $message): self
    {
        return new self(error: $message);
    }
}
