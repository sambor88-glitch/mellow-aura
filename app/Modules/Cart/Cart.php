<?php

namespace App\Modules\Cart;

use App\Modules\Cart\Lines\ProductLine;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * The cart lives in the session: for each line its type, what it points at, the quantity and the
 * customer's own choices. Names, prices and stock are read fresh every time, so the cart never shows
 * an old price and never holds more than is on the shelf.
 */
class Cart
{
    /** The most pieces of one line that has no stock tracking. */
    public const MAX_QUANTITY = 99;

    private const SESSION_KEY = 'cart';

    public function __construct(private Session $session, private LineTypes $types) {}

    /**
     * Lines that can still be bought, keyed and ordered as in the session. A line whose product was hidden
     * or sold out drops out, and a quantity above the limit shrinks to it.
     *
     * @return Collection<string, CartLine>
     */
    public function lines(): Collection
    {
        $stored = $this->stored();
        $lines = [];

        // Rows from before line types were kept are product rows.
        foreach (collect($stored)->groupBy(fn (array $row) => $row['type'] ?? ProductLine::TYPE, preserveKeys: true) as $type => $rows) {
            foreach ($this->types->get((string) $type)?->lines($rows->all()) ?? [] as $key => $line) {
                $lines[$key] = $line;
            }
        }

        $lines = collect($stored)
            ->map(fn (array $row, string $key) => $lines[$key] ?? null)
            ->filter()
            ->map(fn (CartLine $line) => $line->quantity > $line->limit() ? $line->withQuantity($line->limit()) : $line)
            ->filter(fn (CartLine $line) => $line->quantity > 0);

        // Gift wrapping without a piece in a parcel has nothing to wrap; it comes back with the next such piece.
        return $lines->contains(fn (CartLine $line) => $line->needsDelivery())
            ? $lines
            : $lines->reject(fn (CartLine $line) => $line->onlyWithParcel());
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
     * Whether anything in the cart goes in a parcel. Vouchers sent as PDFs alone need no delivery.
     */
    public function needsDelivery(): bool
    {
        return $this->lines()->contains(fn (CartLine $line) => $line->needsDelivery());
    }

    /**
     * Adds the line's quantity, up to its limit, and returns how many pieces went in.
     * A line with the same key grows instead of appearing twice.
     */
    public function add(CartLine $line): int
    {
        $inCart = $this->lines()->get($line->key)->quantity ?? 0;
        $added = max(0, min($line->quantity, $line->limit() - $inCart));

        if ($added > 0) {
            $this->put($line->key, $line->row($inCart + $added));
        }

        return $added;
    }

    /**
     * Sets a line's quantity, at most its limit, and returns the quantity it got.
     * Zero removes the line.
     */
    public function update(string $key, int $quantity): int
    {
        $line = $this->lines()->get($key);

        if ($line === null) {
            return 0;
        }

        $quantity = max(0, min($quantity, $line->limit()));

        if ($quantity === 0) {
            $this->remove($key);
        } else {
            $this->put($key, $line->row($quantity));
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

    /**
     * @param  array<string, mixed>  $row
     */
    private function put(string $key, array $row): void
    {
        $this->session->put(self::SESSION_KEY.'.'.$key, $row);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function stored(): array
    {
        return $this->session->get(self::SESSION_KEY, []);
    }
}
