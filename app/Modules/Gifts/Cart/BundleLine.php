<?php

namespace App\Modules\Gifts\Cart;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineItem;
use App\Modules\Gifts\Models\Bundle;
use App\Modules\Gifts\Models\BundleItem;

/**
 * A gift set in the cart. It sells for the set's price and becomes one order item per part,
 * so each part comes off its own shelf when the payment is confirmed.
 */
class BundleLine extends CartLine
{
    public const TYPE = 'bundle';

    public function __construct(string $key, int $quantity, public readonly Bundle $bundle)
    {
        parent::__construct($key, $quantity);
    }

    public static function keyFor(Bundle $bundle): string
    {
        return 'b'.$bundle->id;
    }

    public function unitPrice(): int
    {
        return $this->bundle->price();
    }

    public function name(): string
    {
        return $this->bundle->name;
    }

    public function details(): ?string
    {
        return 'Zestaw: '.$this->bundle->items
            ->map(fn (BundleItem $item) => $item->variant->product->name.($item->variant->label ? ' ('.$item->variant->label.')' : ''))
            ->join(' + ');
    }

    public function deviations(): array
    {
        return $this->bundle->items
            ->map(fn (BundleItem $item) => $item->variant->product)
            ->filter(fn ($product) => filled($product->deviation))
            ->mapWithKeys(fn ($product) => [$product->name => $product->deviation])
            ->all();
    }

    public function thumbnailUrl(): ?string
    {
        return $this->bundle->items->first()?->variant->product->getFirstMedia('images')?->getAvailableUrl(['thumb']);
    }

    /**
     * As many sets as the scarcest part allows.
     */
    public function limit(): int
    {
        return (int) $this->bundle->items
            ->map(fn (BundleItem $item) => min($item->variant->stock ?? Cart::MAX_QUANTITY, Cart::MAX_QUANTITY))
            ->min();
    }

    public function addedNotice(): string
    {
        return $this->name().' — zestaw w koszyku';
    }

    public function limitNotice(): string
    {
        return match ($limit = $this->limit()) {
            0 => 'Tego zestawu już nie złożę z półki — kolejny zrobię na zamówienie',
            1 => 'To ostatni taki zestaw na półce — kolejny zrobię na zamówienie',
            Cart::MAX_QUANTITY => self::TOO_MANY_NOTICE,
            default => 'Z tego, co na półce, złożę '.$limit.' szt. tego zestawu — więcej zrobię na zamówienie',
        };
    }

    public function row(int $quantity): array
    {
        return ['type' => self::TYPE, 'bundle_id' => $this->bundle->id, 'quantity' => $quantity];
    }

    public function orderItems(): array
    {
        $shares = $this->bundle->partPrices();

        return $this->bundle->items->values()->map(fn (BundleItem $item, int $index) => new LineItem(
            variantId: $item->variant->id,
            name: $item->variant->product->name,
            label: collect([$item->variant->label, 'z zestawu „'.$this->bundle->name.'”'])->filter()->join(' · '),
            quantity: $this->quantity,
            unitPrice: $shares[$index],
            deviation: $item->variant->product->deviation ?: null,
        ))->all();
    }
}
