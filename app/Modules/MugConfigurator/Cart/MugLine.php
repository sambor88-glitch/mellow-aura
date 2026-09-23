<?php

namespace App\Modules\MugConfigurator\Cart;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineItem;
use App\Modules\Localization\Support\Locales;
use App\Modules\MugConfigurator\Support\MugAnalyticsItem;

/**
 * A mug from the configurator: the customer's text in capitals, one line per row, a size and the inside glaze.
 * It is made for the order, so there is no shelf to run out and nothing comes off one.
 */
class MugLine extends CartLine
{
    public const TYPE = 'mug';

    /** The Polish name, as Google Merchant Center and Analytics know it. */
    public const NAME = 'Kubek z napisem';

    /**
     * @param  array{label: string, name: string, price_gross: int, price: int}  $size  in the page's currency and language (MugOptions::sizes)
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
        return $this->size['price'];
    }

    /**
     * Built from the sizes of the page's currency (MugLines), so a mug without a euro price never reaches a euro basket.
     */
    public function pricedIn(string $currency): bool
    {
        return $currency === Locales::currency();
    }

    public function name(): string
    {
        return __('mug-configurator::mug.name');
    }

    public function details(): ?string
    {
        return __('mug-configurator::mug.details', [
            'text' => str_replace("\n", ' / ', $this->text),
            'size' => $this->size['name'],
            'glaze' => mb_strtolower($this->glaze['name']),
        ]);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function thumbnailAlt(): string
    {
        return __('mug-configurator::mug.alt');
    }

    public function analyticsItem(): array
    {
        return MugAnalyticsItem::make($this->size, $this->quantity);
    }

    public function limit(): int
    {
        return Cart::MAX_QUANTITY;
    }

    public function addedNotice(): string
    {
        return __('mug-configurator::mug.added');
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
            name: $this->name(),
            label: $this->size['name'],
            quantity: $this->quantity,
            unitPrice: $this->unitPrice(),
            customText: $this->text,
            customGlaze: $this->glaze['name'],
        )];
    }
}
