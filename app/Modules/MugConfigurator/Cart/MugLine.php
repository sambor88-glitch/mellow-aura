<?php

namespace App\Modules\MugConfigurator\Cart;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineItem;

/**
 * A mug from the configurator: the customer's text in capitals, one line per row, a size and the inside glaze.
 * It is made for the order, so there is no shelf to run out and nothing comes off one.
 */
class MugLine extends CartLine
{
    public const TYPE = 'mug';

    public const NAME = 'Kubek z napisem';

    /**
     * @param  array{label: string, name: string, price_gross: int}  $size
     * @param  array{code: string, name: string}  $glaze
     */
    public function __construct(
        string $key,
        int $quantity,
        public readonly string $text,
        public readonly array $size,
        public readonly array $glaze,
        private readonly ?string $photoUrl,
    ) {
        parent::__construct($key, $quantity);
    }

    /**
     * The same text, size and glaze is one line.
     */
    public static function keyFor(string $text, string $size, string $glaze): string
    {
        return 'm-'.substr(hash('sha256', json_encode([$text, $size, $glaze])), 0, 12);
    }

    public function unitPrice(): int
    {
        return $this->size['price_gross'];
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function details(): ?string
    {
        return '„'.str_replace("\n", ' / ', $this->text).'” · '.$this->size['name'].' · wnętrze '.mb_strtolower($this->glaze['name']);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function thumbnailAlt(): string
    {
        return 'Kubek z wbijanym w glinę napisem';
    }

    public function limit(): int
    {
        return Cart::MAX_QUANTITY;
    }

    public function addedNotice(): string
    {
        return 'Kubek z Twoim napisem w koszyku';
    }

    public function row(int $quantity): array
    {
        return [
            'type' => self::TYPE,
            'text' => $this->text,
            'size' => $this->size['label'],
            'glaze' => $this->glaze['code'],
            'quantity' => $quantity,
        ];
    }

    public function orderItems(): array
    {
        return [new LineItem(
            variantId: null,
            name: self::NAME,
            label: $this->size['name'],
            quantity: $this->quantity,
            unitPrice: $this->unitPrice(),
            customText: $this->text,
            customGlaze: $this->glaze['name'],
        )];
    }
}
