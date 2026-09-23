<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\BreadcrumbStructuredData;
use App\Modules\Shared\Support\DeliveryOffers;
use App\Modules\Shared\Support\MerchantFeed;
use App\Modules\Shared\Support\Money;
use App\Modules\Shared\Support\OfferStructuredData;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\SchemaOrg\ItemAvailability;
use Spatie\SchemaOrg\OfferItemCondition;
use Spatie\SchemaOrg\Schema;

/**
 * JSON-LD for a product page: a ProductGroup with every variant as a Product with its own Offer,
 * plus the breadcrumbs shown on the page. An Offer carries the delivery options and prices from the panel
 * and the return rules, which Google needs to show a product in its free shopping results.
 */
class ProductStructuredData
{
    public static function for(Product $product, Settings $settings): string
    {
        $url = route('product.show', $product);
        $images = $product->getMedia('images')->map(fn (Media $image) => $image->getAvailableUrl(['card']))->all();
        $brand = Schema::brand()->name(MerchantFeed::BRAND);
        // A voucher is a workshop given as a gift and goes out as a PDF or a printed card, so it gets no parcel prices or return rules.
        $shipped = ! $product->isVoucher();
        // The offer in the currency of the page (a euro page with a złoty price is an error in Merchant Center).
        // Delivery and returns are the Polish ones in złoty, so a euro page leaves them out until shipping abroad.
        $currency = Locales::currency();
        $home = $currency === Locales::defaultCurrency();

        $variants = $product->variants->map(function (ProductVariant $variant) use ($product, $url, $images, $brand, $settings, $shipped, $currency, $home) {
            $variant->setRelation('product', $product);

            $offer = Schema::offer()
                ->url($url.'?wariant='.$variant->id)
                ->price(Money::decimal($variant->price($currency)))
                ->priceCurrency($currency)
                ->availability($variant->isInStock() ? ItemAvailability::InStock : ItemAvailability::OutOfStock)
                ->itemCondition(OfferItemCondition::NewCondition);

            if ($shipped && $home) {
                // A mug with the customer's own text is made first, so it gives no delivery time and can't be returned.
                $shipping = OfferStructuredData::shipping(DeliveryOffers::for($variant->price_gross, ! $variant->takesCustomText(), $settings));

                if ($shipping !== []) {
                    $offer->shippingDetails($shipping);
                }

                $offer->hasMerchantReturnPolicy(OfferStructuredData::returns(! $variant->takesCustomText()));
            }

            $item = Schema::product()
                ->name($variant->label === '' ? $product->name : $product->name.' — '.$variant->label)
                // The same id as in the Google Merchant Center feed and in Google Analytics.
                ->sku('v'.$variant->id)
                ->brand($brand)
                ->offers($offer);

            return $images ? $item->image($images[0]) : $item;
        });

        $group = Schema::productGroup()
            ->name($product->name)
            ->url($url)
            ->brand($brand)
            ->productGroupID($product->slug)
            ->hasVariant($variants->all());

        if ($product->description) {
            $group->description($product->description);
        }

        if ($images) {
            $group->image($images);
        }

        return $group->toScript().BreadcrumbStructuredData::for([
            [__('catalog::shop.heading'), route('shop.index')],
            [$product->category->name, route('shop.category', $product->category)],
            [$product->name, $url],
        ]);
    }
}
