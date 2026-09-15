<?php

namespace App\Modules\Cart;

use App\Modules\Catalog\Models\ProductVariant;

final readonly class CartLine
{
    public function __construct(
        public string $key,
        public ProductVariant $variant,
        public int $quantity,
        public ?string $customText,
    ) {}

    public function total(): int
    {
        return $this->variant->price_gross * $this->quantity;
    }
}
