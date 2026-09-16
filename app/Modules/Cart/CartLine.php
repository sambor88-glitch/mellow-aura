<?php

namespace App\Modules\Cart;

/**
 * One line in the cart. A product size is a line, and so is a mug from the configurator or a gift set:
 * each module brings its own kind of line and registers it in LineTypes. Names, prices and limits are
 * read fresh every time the cart is read, so the cart never shows an old price.
 */
abstract class CartLine
{
    public const TOO_MANY_NOTICE = 'Większą liczbę sztuk zrobię na zamówienie — napisz do mnie';

    public function __construct(
        public readonly string $key,
        public private(set) int $quantity,
    ) {}

    /**
     * The price of one piece in grosze.
     */
    abstract public function unitPrice(): int;

    /**
     * The name in the drawer and at checkout.
     */
    abstract public function name(): string;

    /**
     * The line under the name: the size, the text to stamp, who a voucher is for.
     */
    abstract public function details(): ?string;

    abstract public function thumbnailUrl(): ?string;

    /**
     * The most pieces this line can hold right now, e.g. what is on the shelf.
     */
    abstract public function limit(): int;

    /**
     * The row kept in the session for this line with the given quantity. It has to name its type.
     *
     * @return array<string, mixed>
     */
    abstract public function row(int $quantity): array;

    /**
     * What this line becomes on the order: one item, or one per piece of a set.
     * The items' totals add up to the line's total.
     *
     * @return list<LineItem>
     */
    abstract public function orderItems(): array;

    public function thumbnailAlt(): string
    {
        return $this->name();
    }

    /**
     * Features of the pieces in this line nobody would expect, e.g. „nie do zmywarki”, keyed by the product they
     * belong to. The terms want the customer to accept them with a separate checkbox at checkout.
     *
     * @return array<string, string>
     */
    public function deviations(): array
    {
        return [];
    }

    public function total(): int
    {
        return $this->unitPrice() * $this->quantity;
    }

    public function addedNotice(): string
    {
        return $this->name().' — dodane do koszyka';
    }

    /**
     * What the customer hears when they ask for more than the limit.
     */
    public function limitNotice(): string
    {
        return self::TOO_MANY_NOTICE;
    }

    public function withQuantity(int $quantity): static
    {
        $line = clone $this;
        $line->quantity = $quantity;

        return $line;
    }
}
