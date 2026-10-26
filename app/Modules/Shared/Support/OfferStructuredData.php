<?php

namespace App\Modules\Shared\Support;

use Spatie\SchemaOrg\MerchantReturnEnumeration;
use Spatie\SchemaOrg\MerchantReturnPolicy;
use Spatie\SchemaOrg\OfferShippingDetails;
use Spatie\SchemaOrg\QuantitativeValue;
use Spatie\SchemaOrg\ReturnFeesEnumeration;
use Spatie\SchemaOrg\ReturnMethodEnumeration;
use Spatie\SchemaOrg\Schema;

/**
 * The parts of an Offer every product page shares: delivery options and return rules, which Google needs
 * to show a product in its free shopping results.
 */
final class OfferStructuredData
{
    /**
     * @param  list<array{label: string, price: int, handling: ?array{int, int}, transit: ?array{int, int}}>  $delivery  from DeliveryOffers
     * @return list<OfferShippingDetails>
     */
    public static function shipping(array $delivery): array
    {
        return array_map(function (array $option) {
            $details = Schema::offerShippingDetails()
                ->shippingLabel($option['label'])
                ->shippingRate(Schema::monetaryAmount()->value(Money::decimal($option['price']))->currency('PLN'))
                ->shippingDestination(Schema::definedRegion()->addressCountry('PL'));

            if ($option['handling'] === null) {
                return $details;
            }

            $time = Schema::shippingDeliveryTime()->handlingTime(self::days(...$option['handling']));

            return $details->deliveryTime($option['transit'] === null ? $time : $time->transitTime(self::days(...$option['transit'])));
        }, $delivery);
    }

    /**
     * 14 days to withdraw and send the thing back at the customer's cost. Something made with the customer's
     * own text was made only for them, so it can't be returned.
     */
    public static function returns(bool $returnable): MerchantReturnPolicy
    {
        $policy = Schema::merchantReturnPolicy()->applicableCountry('PL');

        if (! $returnable) {
            return $policy->returnPolicyCategory(MerchantReturnEnumeration::MerchantReturnNotPermitted);
        }

        return $policy
            ->returnPolicyCategory(MerchantReturnEnumeration::MerchantReturnFiniteReturnWindow)
            ->merchantReturnDays(14)
            ->returnMethod(ReturnMethodEnumeration::ReturnByMail)
            ->returnFees(ReturnFeesEnumeration::ReturnFeesCustomerResponsibility);
    }

    private static function days(int $min, int $max): QuantitativeValue
    {
        return Schema::quantitativeValue()->minValue($min)->maxValue($max)->unitCode('DAY');
    }
}
