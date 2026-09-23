<?php

namespace App\Modules\Cart\Lines;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineItem;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\VariantAnalyticsItem;

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
        return (int) $this->variant->price();
    }

    public function pricedIn(string $currency): bool
    {
        return $this->variant->price($currency) !== null;
    }

    // A piece put in on a Polish page may have no English text yet; the basket still names it (see HasTranslations).
    public function name(): string
    {
        return $this->variant->product->inPageLanguageOrPolish('name');
    }

    public function details(): ?string
    {
        return $this->customText !== null ? '„'.$this->customText.'”' : ($this->variant->inPageLanguageOrPolish('label') ?: null);
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

    public function analyticsItem(): array
    {
        return VariantAnalyticsItem::make($this->variant, $this->quantity);
    }

    public function needsDelivery(): bool
    {
        return $this->variant->needsDelivery();
    }

    public function limit(): int
    {
        return min($this->variant->stock ?? Cart::MAX_QUANTITY, Cart::MAX_QUANTITY);
    }

    public function limitNotice(): string
    {
        return match ($this->variant->stock) {
            null => self::tooManyNotice(),
            0 => __('cart::line.sold_out'),
            1 => __('cart::line.last_one'),
            default => __('cart::line.on_shelf', ['count' => $this->variant->stock]),
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
            label: $this->variant->inPageLanguageOrPolish('label'),
            quantity: $this->quantity,
            unitPrice: $this->unitPrice(),
            customText: $this->customText,
            // Never dropped for want of a translation: the checkout asks to accept it.
            deviation: $this->variant->product->inPageLanguageOrPolish('deviation') ?: null,
        )];
    }
}
