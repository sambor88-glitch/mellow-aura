<?php

namespace App\Modules\MugConfigurator\Support;

use App\Modules\MugConfigurator\Cart\MugLine;
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

        $variants = $options->sizes()->map(function (array $size) use ($url, $brand, $photo, $settings) {
            $offer = Schema::offer()
                ->url($url.'?rozmiar='.MugOptions::sizeKey($size['label']))
                ->price(Money::decimal($size['price_gross']))
                ->priceCurrency('PLN')
                ->availability(ItemAvailability::InStock)
                ->itemCondition(OfferItemCondition::NewCondition)
                ->hasMerchantReturnPolicy(OfferStructuredData::returns(false));

            $shipping = OfferStructuredData::shipping(DeliveryOffers::for($size['price_gross'], false, $settings));

            if ($shipping !== []) {
                $offer->shippingDetails($shipping);
            }

            $item = Schema::product()
                ->name(MugLine::NAME.' — '.$size['name'])
                ->sku('mug-'.MugOptions::sizeKey($size['label']))
                ->brand($brand)
                ->offers($offer);

            return $photo ? $item->image($photo) : $item;
        });

        $group = Schema::productGroup()
            ->name(MugLine::NAME)
            ->url($url)
            ->brand($brand)
            ->productGroupID('kubek-z-napisem')
            ->hasVariant($variants->all());

        if (filled($lead = $settings->get('text_mug_lead'))) {
            $group->description((string) $lead);
        }

        if ($photo) {
            $group->image($photo);
        }

        return $group->toScript().BreadcrumbStructuredData::for([
            ['Strona główna', url('/')],
            [MugLine::NAME, $url],
        ]);
    }
}
