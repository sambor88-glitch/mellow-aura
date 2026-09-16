<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\BreadcrumbStructuredData;
use App\Modules\Shared\Support\DispatchTime;
use App\Modules\Shared\Support\Money;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\SchemaOrg\ItemAvailability;
use Spatie\SchemaOrg\MerchantReturnEnumeration;
use Spatie\SchemaOrg\MerchantReturnPolicy;
use Spatie\SchemaOrg\OfferItemCondition;
use Spatie\SchemaOrg\OfferShippingDetails;
use Spatie\SchemaOrg\QuantitativeValue;
use Spatie\SchemaOrg\ReturnFeesEnumeration;
use Spatie\SchemaOrg\ReturnMethodEnumeration;
use Spatie\SchemaOrg\Schema;

/**
 * JSON-LD for a product page: a ProductGroup with every variant as a Product with its own Offer,
 * plus the breadcrumbs shown on the page. An Offer carries the delivery options and prices from the panel
 * and the return rules, which Google needs to show a product in its free shopping results.
 */
class ProductStructuredData
{
    // Pickup at the studio is not a delivery to an address, so it stays out of the shipping details.
    private const PICKUP = 'studio_pickup';

    public static function for(Product $product, Settings $settings): string
    {
        $url = route('product.show', $product);
        $images = $product->getMedia('images')->map(fn (Media $image) => $image->getAvailableUrl(['card']))->all();
        $brand = Schema::brand()->name('MellowAura');
        // A voucher is a workshop given as a gift and goes out as a PDF or a printed card, so it gets no parcel prices or return rules.
        $shipped = ! $product->isVoucher();

        $variants = $product->variants->map(function (ProductVariant $variant) use ($product, $url, $images, $brand, $settings, $shipped) {
            $variant->setRelation('product', $product);

            $offer = Schema::offer()
                ->url($url.'?wariant='.$variant->id)
                ->price(Money::decimal($variant->price_gross))
                ->priceCurrency('PLN')
                ->availability($variant->isInStock() ? ItemAvailability::InStock : ItemAvailability::OutOfStock)
                ->itemCondition(OfferItemCondition::NewCondition);

            if ($shipped) {
                $shipping = self::shipping($variant, $settings);

                if ($shipping !== []) {
                    $offer->shippingDetails($shipping);
                }

                $offer->hasMerchantReturnPolicy(self::returns($variant));
            }

            $item = Schema::product()
                ->name($variant->label === '' ? $product->name : $product->name.' — '.$variant->label)
                ->sku($product->slug.'-'.$variant->id)
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
            ['Sklep', route('shop.index')],
            [$product->category->name, route('shop.category', $product->category)],
            [$product->name, $url],
        ]);
    }

    /**
     * Each delivery option in Poland at its price, free from the threshold. An item from the shelf also says how long
     * it takes to leave the studio and to travel, as the checkout shows it under the option („1–2 dni robocze”).
     * A mug with the customer's own text is made first, so it gives no delivery time.
     *
     * @return list<OfferShippingDetails>
     */
    private static function shipping(ProductVariant $variant, Settings $settings): array
    {
        $threshold = (int) $settings->get('free_shipping_threshold', 0);
        $free = $threshold > 0 && $variant->price_gross >= $threshold;
        $handling = $variant->takesCustomText() ? null : DispatchTime::days($settings);

        return collect((array) $settings->get('shipping_methods', []))
            ->filter(fn (mixed $method) => is_array($method) && filled($method['label'] ?? null) && ($method['code'] ?? null) !== self::PICKUP)
            ->map(function (array $method) use ($free, $handling) {
                $details = Schema::offerShippingDetails()
                    ->shippingLabel($method['label'])
                    ->shippingRate(Schema::monetaryAmount()->value(Money::decimal($free ? 0 : (int) ($method['price_gross'] ?? 0)))->currency('PLN'))
                    ->shippingDestination(Schema::definedRegion()->addressCountry('PL'));

                if ($handling === null) {
                    return $details;
                }

                $time = Schema::shippingDeliveryTime()->handlingTime(self::days(...$handling));
                $transit = self::transitDays($method['note'] ?? null);

                return $details->deliveryTime($transit === null ? $time : $time->transitTime(self::days(...$transit)));
            })
            ->values()
            ->all();
    }

    /**
     * The days in a delivery option's note from the panel: „1–2 dni robocze” → [1, 2], „Do rąk, 1 dzień” → [1, 1].
     *
     * @return array{int, int}|null
     */
    private static function transitDays(mixed $note): ?array
    {
        if (! is_string($note) || ! preg_match('/(\d+)(?:\s*[–-]\s*(\d+))?\s*(?:dni|dzień)/u', $note, $match)) {
            return null;
        }

        $min = (int) $match[1];
        $max = filled($match[2] ?? null) ? (int) $match[2] : $min;

        return [min($min, $max), max($min, $max)];
    }

    private static function days(int $min, int $max): QuantitativeValue
    {
        return Schema::quantitativeValue()->minValue($min)->maxValue($max)->unitCode('DAY');
    }

    /**
     * 14 days to withdraw and send the thing back at the customer's cost. A mug with the customer's own text
     * was made only for them, so it can't be returned.
     */
    private static function returns(ProductVariant $variant): MerchantReturnPolicy
    {
        $policy = Schema::merchantReturnPolicy()->applicableCountry('PL');

        if ($variant->takesCustomText()) {
            return $policy->returnPolicyCategory(MerchantReturnEnumeration::MerchantReturnNotPermitted);
        }

        return $policy
            ->returnPolicyCategory(MerchantReturnEnumeration::MerchantReturnFiniteReturnWindow)
            ->merchantReturnDays(14)
            ->returnMethod(ReturnMethodEnumeration::ReturnByMail)
            ->returnFees(ReturnFeesEnumeration::ReturnFeesCustomerResponsibility);
    }
}
