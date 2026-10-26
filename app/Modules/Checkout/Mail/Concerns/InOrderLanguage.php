<?php

namespace App\Modules\Checkout\Mail\Concerns;

use App\Modules\Checkout\Models\Order;

/**
 * A mail to the customer in the language she ordered in. Laravel renders the whole mail — subject, amounts, the
 * words from lang files — in that language; the letter itself has a template per language, since it is written
 * text, not an interface: checkout::mail.en.order-confirmed next to checkout::mail.order-confirmed.
 */
trait InOrderLanguage
{
    /** Languages with their own templates; any other order gets the Polish letter. */
    private const WRITTEN_IN = ['en'];

    protected function inOrderLanguage(Order $order): void
    {
        $this->locale($order->locale ?: 'pl');
    }

    protected function template(string $name): string
    {
        return 'checkout::mail.'.(in_array($this->locale, self::WRITTEN_IN, true) ? $this->locale.'.' : '').$name;
    }
}
