<?php

namespace App\Modules\Cart;

use App\Modules\Checkout\Models\OrderItem;
use Closure;

/**
 * One order item a cart line turns into, with copies of what the customer saw, so a later change
 * in the shop never rewrites the order. A null variant means the item is made for the order
 * (gift wrapping, a mug from the configurator) and nothing comes off a shelf.
 * A line that keeps more about an item in its own module's tables gets the saved item in $placed, inside the order's
 * transaction, e.g. who a gift choice card is for and when it goes out (MA-235).
 */
final readonly class LineItem
{
    public function __construct(
        public ?int $variantId,
        public string $name,
        public string $label,
        public int $quantity,
        public int $unitPrice,
        public ?string $customText = null,
        public ?string $customGlaze = null,
        public ?string $recipientName = null,
        public ?string $dedication = null,
        public ?string $senderName = null,
        public ?string $deviation = null,
        /** @var ?Closure(OrderItem): void */
        public ?Closure $placed = null,
        // One piece of the product in the language of the order („Wazon”); null keeps $name on its page (MA-291).
        public ?string $pieceName = null,
    ) {}
}
