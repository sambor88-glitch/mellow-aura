<?php

namespace App\Modules\Shared\Support;

/**
 * One thing to buy in the feed for Google Merchant Center: a product size, a mug size from the configurator
 * or a gift set. Its id is also the item_id Google Analytics gets, so both describe the same thing.
 */
final readonly class MerchantItem
{
    /** The return policy set up in Merchant Center for things made for one customer, which can't be returned. */
    public const NOT_RETURNABLE = 'personalizowane';

    /**
     * @param  list<string>  $additionalImages
     * @param  list<array{label: string, price: int, handling: ?array{int, int}, transit: ?array{int, int}}>  $delivery
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $link,
        public ?string $image,
        public int $price,
        public bool $available,
        public array $delivery,
        public array $additionalImages = [],
        public ?int $salePrice = null,
        public ?int $googleCategory = null,
        public ?string $productType = null,
        public bool $isBundle = false,
        public bool $returnable = true,
    ) {}
}
