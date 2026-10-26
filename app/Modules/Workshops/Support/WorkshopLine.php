<?php

namespace App\Modules\Workshops\Support;

use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineItem;
use App\Modules\Workshops\Models\WorkshopDate;

/**
 * Seats on a workshop date as the one line of a booking's order. It never goes into the cart: the booking form
 * hands it straight to Checkout\Actions\PlaceOrder, so the order keeps the name, the date and the price she saw.
 */
class WorkshopLine extends CartLine
{
    public const TYPE = 'workshop';

    /**
     * @param  array{name: string, price_gross: int}  $workshop  a row of WorkshopTypes::all()
     */
    public function __construct(private readonly WorkshopDate $date, private readonly array $workshop, int $seats)
    {
        parent::__construct(self::TYPE.'-'.$date->id, $seats);
    }

    public function unitPrice(): int
    {
        return $this->workshop['price_gross'];
    }

    public function name(): string
    {
        return $this->workshop['name'];
    }

    /** „sob. 31 października, 11:00 – 13:00”. */
    public function details(): ?string
    {
        return $this->date->dayLabel().', '.$this->date->timeLabel();
    }

    public function thumbnailUrl(): ?string
    {
        return null;
    }

    public function limit(): int
    {
        return $this->date->seatsLeft();
    }

    /** A workshop is attended, not delivered: the order needs no address or parcel. */
    public function needsDelivery(): bool
    {
        return false;
    }

    public function row(int $quantity): array
    {
        return ['type' => self::TYPE, 'workshop_date_id' => $this->date->id, 'quantity' => $quantity];
    }

    public function orderItems(): array
    {
        return [new LineItem(variantId: null, name: $this->name(), label: (string) $this->details(), quantity: $this->quantity, unitPrice: $this->unitPrice())];
    }
}
