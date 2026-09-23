<?php

namespace App\Modules\MugConfigurator\Support;

use App\Modules\Catalog\Enums\GoogleCategory;
use App\Modules\MugConfigurator\Cart\MugLine;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\DeliveryOffers;
use App\Modules\Shared\Support\MerchantItem;

/**
 * The mug with the customer's own text for the Google Merchant Center feed, once per size, each linked to the
 * configurator with that size chosen. It is made for one customer, so it gives no delivery time and can't be returned.
 */
class MugMerchantItems
{
    public function __construct(private MugOptions $options, private Settings $settings) {}

    /**
     * @return iterable<MerchantItem>
     */
    public function __invoke(): iterable
    {
        // Without glazes the page has nothing to sell.
        if ($this->options->glazes()->isEmpty()) {
            return;
        }

        foreach ($this->options->sizes() as $size) {
            yield new MerchantItem(
                id: 'mug-'.MugOptions::sizeKey($size['label']),
                title: MugLine::NAME.' — '.$size['name'],
                description: (string) ($this->settings->get('text_mug_lead') ?: MugLine::NAME),
                link: route('mug.index', ['rozmiar' => MugOptions::sizeKey($size['label'])]),
                image: $this->options->photoUrl(),
                price: $size['price_gross'],
                available: true,
                delivery: DeliveryOffers::for($size['price_gross'], false, $this->settings),
                googleCategory: GoogleCategory::Mugs->value,
                productType: 'Ceramika > '.MugLine::NAME,
                returnable: false,
            );
        }
    }
}
