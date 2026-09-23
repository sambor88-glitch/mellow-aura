<?php

namespace App\Modules\MugConfigurator\Support;

use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\BreadcrumbStructuredData;
use App\Modules\Shared\Support\DeliveryOffers;
use App\Modules\Shared\Support\MerchantFeed;
use App\Modules\Shared\Support\Money;
use App\Modules\Shared\Support\OfferStructuredData;
use Spatie\SchemaOrg\ItemAvailability;
use Spatie\SchemaOrg\OfferItemCondition;
use Spatie\SchemaOrg\Schema;

/**
 * JSON-LD for /kubek-z-napisem: like a product page, a ProductGroup with a Product and an Offer for every size,
 * so Google reads each size's price on the page its shopping results link to. The mug is made with the customer's
 * text, so it promises no delivery time and can't be returned.
 */
class MugStructuredData
{
    public static function for(MugOptions $options, Settings $settings): string
    {
        $url = route('mug.index');
        $brand = Schema::brand()->name(MerchantFeed::BRAND);
        $photo = $options->photoUrl();

        // In the currency of the page; delivery is Polish and in złoty, so a euro page leaves it out (as for products).
        $currency = Locales::currency();
        $home = $currency === Locales::defaultCurrency();
        $name = __('mug-configurator::mug.name');

        $variants = $options->sizes()->map(function (array $size) use ($url, $brand, $photo, $settings, $currency, $home, $name) {
            $offer = Schema::offer()
                ->url($url.'?rozmiar='.MugOptions::sizeKey($size['label']))
                ->price(Money::decimal($size['price']))
                ->priceCurrency($currency)
                ->availability(ItemAvailability::InStock)
                ->itemCondition(OfferItemCondition::NewCondition)
                ->hasMerchantReturnPolicy(OfferStructuredData::returns(false));

            $shipping = $home ? OfferStructuredData::shipping(DeliveryOffers::for($size['price_gross'], false, $settings)) : [];

            if ($shipping !== []) {
                $offer->shippingDetails($shipping);
            }

            $item = Schema::product()
                ->name($name.' — '.$size['name'])
                ->sku('mug-'.MugOptions::sizeKey($size['label']))
                ->brand($brand)
                ->offers($offer);

            return $photo ? $item->image($photo) : $item;
        });

        $group = Schema::productGroup()
            ->name($name)
            ->url($url)
            ->brand($brand)
            ->productGroupID('kubek-z-napisem')
            ->hasVariant($variants->all());

        // Kasia's lead from the panel is Polish; an English page describes the mug in its own words.
        if (filled($lead = $home ? $settings->get('text_mug_lead') : __('mug-configurator::mug.description'))) {
            $group->description((string) $lead);
        }

        if ($photo) {
            $group->image($photo);
        }

        return $group->toScript().BreadcrumbStructuredData::for([
            [__('mug-configurator::mug.home'), route('home')],
            [$name, $url],
        ]);
    }
}
