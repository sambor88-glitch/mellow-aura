<?php

namespace App\Modules\Cart;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * The cart lives in the session: variant ids, quantities and the customer's own text.
 * Names, prices and stock are read fresh from the catalogue every time, so the cart never
 * shows an old price and never holds more than is on the shelf.
 */
class Cart
{
    /** The most pieces of one variant that has no stock tracking. */
    public const MAX_QUANTITY = 99;

    private const SESSION_KEY = 'cart';

    public function __construct(private Session $session) {}

    /**
     * Lines that can still be bought, keyed as in the session. A line whose product was hidden
     * or sold out drops out, and a quantity above the shelf shrinks to it.
     *
     * @return Collection<string, CartLine>
     */
    public function lines(): Collection
    {
        $stored = $this->stored();

        if ($stored === []) {
            return collect();
        }

        $variants = ProductVariant::query()
            ->with('product.media')
            ->whereKey(array_column($stored, 'variant_id'))
            ->whereRelation('product', 'is_published', true)
            ->get()
            ->keyBy('id');

        return collect($stored)
            ->map(function (array $row, string $key) use ($variants) {
                $variant = $variants->get($row['variant_id']);
                $quantity = $variant ? min($row['quantity'], self::available($variant)) : 0;

                return $quantity > 0 ? new CartLine($key, $variant, $quantity, $row['custom_text']) : null;
            })
            ->filter();
    }

    public function count(): int
    {
        return (int) $this->lines()->sum('quantity');
    }

    public function subtotal(): int
    {
        return (int) $this->lines()->sum(fn (CartLine $line) => $line->total());
    }

    /**
     * Adds up to what is on the shelf and returns how many pieces went in.
     * The same variant with a different text is a separate line.
     */
    public function add(ProductVariant $variant, int $quantity, ?string $customText = null): int
    {
        $key = 'v'.$variant->id.($customText === null ? '' : '-'.substr(hash('sha256', $customText), 0, 12));
        $inCart = $this->lines()->get($key)->quantity ?? 0;
        $added = max(0, min($quantity, self::available($variant) - $inCart));

        if ($added > 0) {
            $this->put($key, ['variant_id' => $variant->id, 'quantity' => $inCart + $added, 'custom_text' => $customText]);
        }

        return $added;
    }

    /**
     * Sets a line's quantity, at most what is on the shelf, and returns the quantity it got.
     * Zero removes the line.
     */
    public function update(string $key, int $quantity): int
    {
        $line = $this->lines()->get($key);

        if ($line === null) {
            return 0;
        }

        $quantity = max(0, min($quantity, self::available($line->variant)));

        if ($quantity === 0) {
            $this->remove($key);
        } else {
            $this->put($key, ['variant_id' => $line->variant->id, 'quantity' => $quantity, 'custom_text' => $line->customText]);
        }

        return $quantity;
    }

    public function remove(string $key): void
    {
        $this->session->forget(self::SESSION_KEY.'.'.$key);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    private static function available(ProductVariant $variant): int
    {
        return min($variant->stock ?? self::MAX_QUANTITY, self::MAX_QUANTITY);
    }

    /**
     * @param  array{variant_id: int, quantity: int, custom_text: ?string}  $row
     */
    private function put(string $key, array $row): void
    {
        $this->session->put(self::SESSION_KEY.'.'.$key, $row);
    }

    /**
     * @return array<string, array{variant_id: int, quantity: int, custom_text: ?string}>
     */
    private function stored(): array
    {
        return $this->session->get(self::SESSION_KEY, []);
    }
}
