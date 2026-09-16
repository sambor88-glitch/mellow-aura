<?php

namespace App\Modules\Gifts\Cart;

use App\Modules\Cart\LineItem;
use App\Modules\Cart\Lines\ProductLine;
use App\Modules\Catalog\Models\ProductVariant;

/**
 * A voucher from the shop with the names and dedication the customer typed on its page.
 * Each set of names and dedication is a separate line, because each voucher is printed with its own.
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
        public readonly ?string $senderName = null,
    ) {
        parent::__construct($key, $quantity, $variant);
    }

    public static function keyForVoucher(ProductVariant $variant, ?string $recipientName, ?string $dedication, ?string $senderName = null): string
    {
        if ($recipientName === null && $dedication === null && $senderName === null) {
            return self::keyFor($variant, null);
        }

        // A line from before „od kogo” keeps its key.
        return self::keyFor($variant, json_encode($senderName === null ? [$recipientName, $dedication] : [$recipientName, $dedication, $senderName]));
    }

    public function details(): ?string
    {
        return collect([
            $this->variant->label ?: null,
            $this->recipientName !== null ? 'dla: '.$this->recipientName : null,
            $this->senderName !== null ? 'od: '.$this->senderName : null,
            $this->dedication !== null ? 'dedykacja: „'.$this->dedication.'”' : null,
        ])->filter()->join(' · ') ?: null;
    }

    public function row(int $quantity): array
    {
        return [
            ...parent::row($quantity),
            'recipient_name' => $this->recipientName,
            'dedication' => $this->dedication,
            'sender_name' => $this->senderName,
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
            senderName: $this->senderName,
        )];
    }
}
