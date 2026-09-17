<?php

namespace App\Modules\Gifts\Cart;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineType;
use App\Modules\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Gift wrapping. Without a price in the panel it is not offered, and it leaves the cart.
 */
class GiftWrapLines implements LineType
{
    public function __construct(private Settings $settings) {}

    public function lines(array $rows): iterable
    {
        if (! $this->offered()) {
            return;
        }

        foreach ($rows as $key => $row) {
            if ($key === GiftWrapLine::KEY) {
                yield $key => $this->line((int) $row['quantity']);
            }
        }
    }

    public function fromRequest(Request $request): CartLine
    {
        abort_unless($this->offered(), 404);

        // Wrapping goes on pieces in the parcel: first something to wrap.
        $cart = app(Cart::class);

        if (! $cart->needsDelivery()) {
            throw ValidationException::withMessages(['type' => $cart->lines()->isEmpty()
                ? 'Najpierw dodaj do koszyka to, co mam zapakować'
                : 'Voucher w PDF przychodzi mailem, więc nie ma czego pakować']);
        }

        return $this->line(1);
    }

    public function offered(): bool
    {
        return (int) $this->settings->get('gift_wrap_price', 0) > 0;
    }

    private function line(int $quantity): GiftWrapLine
    {
        return new GiftWrapLine($quantity, (string) $this->settings->get('text_gift_wrap_heading', 'Pakowanie na prezent'), (int) $this->settings->get('gift_wrap_price'));
    }
}
