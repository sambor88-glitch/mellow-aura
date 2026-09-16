<?php

namespace App\Modules\Gifts\Cart;

use App\Modules\Cart\LineItem;
use App\Modules\Cart\Lines\ProductLine;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * A voucher from the shop with the name and dedication the customer typed on its page.
 * Each name and dedication is a separate line, because each voucher is printed with its own.
 */
class VoucherLine extends ProductLine
{
    public const TYPE = 'voucher';

    public function __construct(
        string $key,
        int $quantity,
        ProductVariant $variant,
        public readonly ?string $recipientName = null,
        public readonly ?string $dedication = null,
    ) {
        parent::__construct($key, $quantity, $variant);
    }

    public static function keyForVoucher(ProductVariant $variant, ?string $recipientName, ?string $dedication): string
    {
        return self::keyFor($variant, $recipientName === null && $dedication === null ? null : json_encode([$recipientName, $dedication]));
    }

    public function details(): ?string
    {
        return collect([
            $this->variant->label ?: null,
            $this->recipientName !== null ? 'dla: '.$this->recipientName : null,
            $this->dedication !== null ? 'dedykacja: „'.$this->dedication.'”' : null,
        ])->filter()->join(' · ') ?: null;
    }

    public function row(int $quantity): array
    {
        return [
            ...parent::row($quantity),
            'recipient_name' => $this->recipientName,
            'dedication' => $this->dedication,
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
            recipientName: $this->recipientName,
            dedication: $this->dedication,
        )];
    }
}
