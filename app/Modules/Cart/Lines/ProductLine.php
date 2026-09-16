<?php

namespace App\Modules\Cart\Lines;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineItem;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * A size of a product from the catalogue, with the customer's own text when the product is stamped.
 */
class ProductLine extends CartLine
{
    public const TYPE = 'product';

    public function __construct(
        string $key,
        int $quantity,
        public readonly ProductVariant $variant,
        public readonly ?string $customText = null,
    ) {
        parent::__construct($key, $quantity);
    }

    /**
     * The same variant with a different text is a separate line.
     */
    public static function keyFor(ProductVariant $variant, ?string $customText = null): string
    {
        return 'v'.$variant->id.($customText === null ? '' : '-'.substr(hash('sha256', $customText), 0, 12));
    }

    public function unitPrice(): int
    {
        return $this->variant->price_gross;
    }

    public function name(): string
    {
        return $this->variant->product->name;
    }

    public function details(): ?string
    {
        return $this->customText !== null ? '„'.$this->customText.'”' : ($this->variant->label ?: null);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->variant->product->getFirstMedia('images')?->getAvailableUrl(['thumb']);
    }

    public function thumbnailAlt(): string
    {
        return $this->variant->product->getFirstMedia('images')?->getCustomProperty('alt') ?: $this->name();
    }

    public function deviations(): array
    {
        $deviation = $this->variant->product->deviation;

        return filled($deviation) ? [$this->name() => $deviation] : [];
    }

    public function limit(): int
    {
        return min($this->variant->stock ?? Cart::MAX_QUANTITY, Cart::MAX_QUANTITY);
    }

    public function limitNotice(): string
    {
        return match ($this->variant->stock) {
            null => self::TOO_MANY_NOTICE,
            0 => 'Tej sztuki już nie ma na półce — kolejną zrobię na zamówienie',
            1 => 'To ostatnia sztuka — kolejną zrobię na zamówienie',
            default => 'Na półce mam '.$this->variant->stock.' szt. — więcej zrobię na zamówienie',
        };
    }

    public function row(int $quantity): array
    {
        return [
            'type' => static::TYPE,
            'variant_id' => $this->variant->id,
            'quantity' => $quantity,
            'custom_text' => $this->customText,
        ];
    }

    public function orderItems(): array
    {
        return [new LineItem(
            variantId: $this->variant->id,
            name: $this->name(),
            label: $this->variant->label,
            quantity: $this->quantity,
            unitPrice: $this->unitPrice(),
            customText: $this->customText,
            deviation: $this->variant->product->deviation ?: null,
        )];
    }
}
