<?php

namespace App\Modules\CustomOrders\Support;

use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineItem;
use App\Modules\CustomOrders\Models\PaymentLink;
use App\Modules\Shared\Support\Money;

/**
 * A payment link as the one line of its order. It never goes into the cart: the link's page hands it straight to
 * Checkout\Actions\PlaceOrder, so the order keeps what the money is for and how much the quote was.
 */
class PaymentLine extends CartLine
{
    public const TYPE = 'custom_order_payment';

    public function __construct(private readonly PaymentLink $link)
    {
        parent::__construct(self::TYPE.'-'.$link->id, 1);
    }

    public function unitPrice(): int
    {
        return $this->link->amount;
    }

    /** „Zamówienie indywidualne: Kubki z napisem”. */
    public function name(): string
    {
        return 'Zamówienie indywidualne: '.$this->link->customOrderRequest->kind->label();
    }

    /** „Wpłata do wyceny 900 zł”. */
    public function details(): ?string
    {
        $quote = $this->link->customOrderRequest->quote;

        return $quote === null ? 'Wpłata' : 'Wpłata do wyceny '.Money::format($quote);
    }

    public function thumbnailUrl(): ?string
    {
        return null;
    }

    public function limit(): int
    {
        return 1;
    }

    /** Whatever Kasia makes is sent when it is ready; this payment itself delivers nothing. */
    public function needsDelivery(): bool
    {
        return false;
    }

    public function row(int $quantity): array
    {
        return ['type' => self::TYPE, 'payment_link_id' => $this->link->id, 'quantity' => $quantity];
    }

    public function orderItems(): array
    {
        return [new LineItem(variantId: null, name: $this->name(), label: (string) $this->details(), quantity: 1, unitPrice: $this->unitPrice())];
    }
}
