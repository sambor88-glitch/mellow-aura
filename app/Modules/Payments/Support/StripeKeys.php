<?php

namespace App\Modules\Payments\Support;

/**
 * Whether the shop has what it takes to charge anyone. Both keys are needed: the secret one to start
 * a payment, the publishable one for the browser to confirm it. One without the other is a half-set-up
 * shop, and a half-set-up shop must not pretend it can take money.
 */
class StripeKeys
{
    public static function configured(): bool
    {
        return filled(self::secret()) && filled(self::publishable());
    }

    public static function publishable(): ?string
    {
        return config('services.stripe.key');
    }

    public static function secret(): ?string
    {
        return config('services.stripe.secret');
    }
}
