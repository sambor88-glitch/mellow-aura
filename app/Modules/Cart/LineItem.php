<?php

namespace App\Modules\Cart;

/**
 * One order item a cart line turns into, with copies of what the customer saw, so a later change
 * in the shop never rewrites the order. A null variant means the item is made for the order
 * (gift wrapping, a mug from the configurator) and nothing comes off a shelf.
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
    ) {}
}
