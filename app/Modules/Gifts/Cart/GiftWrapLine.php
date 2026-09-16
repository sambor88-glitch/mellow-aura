<?php

namespace App\Modules\Gifts\Cart;

use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineItem;

/**
 * Wrapping the whole order as a gift, once per order, for the price set in the panel.
 */
class GiftWrapLine extends CartLine
{
    public const TYPE = 'gift_wrap';

    public const KEY = 'wrap';

    public function __construct(int $quantity, private readonly string $label, private readonly int $price)
    {
        parent::__construct(self::KEY, $quantity);
    }

    public function unitPrice(): int
    {
        return $this->price;
    }

    public function name(): string
    {
        return $this->label;
    }

    public function details(): ?string
    {
        return null;
    }

    public function thumbnailUrl(): ?string
    {
        return null;
    }

    public function limit(): int
    {
        return 1;
    }

    public function addedNotice(): string
    {
        return $this->label.' — dodane do zamówienia';
    }

    public function limitNotice(): string
    {
        return $this->label.' jest już w koszyku';
    }

    public function row(int $quantity): array
    {
        return ['type' => self::TYPE, 'quantity' => $quantity];
    }

    public function orderItems(): array
    {
        return [new LineItem(variantId: null, name: $this->label, label: '', quantity: $this->quantity, unitPrice: $this->price)];
    }
}
