<?php

namespace App\Modules\Gifts\Cart;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Cart\LineItem;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Gifts\Models\GiftChoice;
use Carbon\CarbonImmutable;

/**
 * A gift choice card (MA-235): a mug of one size with the delivery to the person it is for, who chooses the text
 * and the glaze herself. Nothing goes in the buyer's parcel: the card comes as a PDF or by e-mail on the chosen day.
 * Each set of names, dedication and delivery is its own line; a card that goes to one address by e-mail is one piece.
 * Złoty only, like the rest of „Podaruj wybór”.
 */
class GiftChoiceLine extends CartLine
{
    public const TYPE = 'gift_choice';

    /**
     * @param  array{label: string, name: string, price_gross: int}  $size  a złoty size of MugOptions::sizes()
     */
    public function __construct(
        string $key,
        int $quantity,
        public readonly array $size,
        public readonly int $deliveryGross,
        public readonly ?string $recipientName,
        public readonly ?string $senderName,
        public readonly ?string $dedication,
        public readonly ?string $recipientEmail,
        public readonly ?string $sendOn,
        private readonly ?string $photoUrl,
    ) {
        parent::__construct($key, $quantity);
    }

    /**
     * @param  list<?string>  $details  the names, dedication, address and day
     */
    public static function keyFor(string $size, array $details): string
    {
        return 'gc-'.substr(hash('sha256', json_encode([$size, ...$details])), 0, 12);
    }

    public function unitPrice(): int
    {
        return $this->size['price_gross'] + $this->deliveryGross;
    }

    public function name(): string
    {
        return __('gifts::choice.line.name');
    }

    public function details(): ?string
    {
        return collect([
            $this->size['name'],
            $this->recipientName !== null ? __('gifts::choice.line.for', ['name' => $this->recipientName]) : null,
            $this->senderName !== null ? __('gifts::choice.line.from', ['name' => $this->senderName]) : null,
            $this->sendOn !== null
                ? __('gifts::choice.line.by_email', ['date' => CarbonImmutable::parse($this->sendOn)->translatedFormat('j F')])
                : __('gifts::choice.line.print'),
        ])->filter()->join(' · ');
    }

    public function thumbnailUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function thumbnailAlt(): string
    {
        return __('mug-configurator::mug.alt');
    }

    public function limit(): int
    {
        return $this->recipientEmail !== null ? 1 : Cart::MAX_QUANTITY;
    }

    public function needsDelivery(): bool
    {
        return false;
    }

    public function addedNotice(): string
    {
        return __('gifts::choice.line.added');
    }

    public function row(int $quantity): array
    {
        return [
            'type' => self::TYPE,
            'size' => $this->size['label'],
            'recipient_name' => $this->recipientName,
            'sender_name' => $this->senderName,
            'dedication' => $this->dedication,
            'recipient_email' => $this->recipientEmail,
            'send_on' => $this->sendOn,
            'quantity' => $quantity,
        ];
    }

    /**
     * One item with the price of the card, which keeps one card per piece in Gifts, with no code until it is paid.
     */
    public function orderItems(): array
    {
        return [new LineItem(
            variantId: null,
            name: $this->name(),
            label: $this->size['name'],
            quantity: $this->quantity,
            unitPrice: $this->unitPrice(),
            recipientName: $this->recipientName,
            dedication: $this->dedication,
            senderName: $this->senderName,
            placed: function (OrderItem $item) {
                for ($piece = 0; $piece < $item->quantity; $piece++) {
                    GiftChoice::create([
                        'order_item_id' => $item->id,
                        'size_label' => $this->size['label'],
                        'size_name' => $this->size['name'],
                        'price_gross' => $item->unit_price_gross,
                        'recipient_name' => $this->recipientName,
                        'sender_name' => $this->senderName,
                        'dedication' => $this->dedication,
                        'recipient_email' => $this->recipientEmail,
                        'send_on' => $this->sendOn,
                    ]);
                }
            },
        )];
    }
}
