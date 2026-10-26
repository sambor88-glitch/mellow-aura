<?php

namespace App\Modules\Shared\Support;

use Spatie\SchemaOrg\Offer;
use Spatie\SchemaOrg\Schema;

/**
 * JSON-LD for a service page — workshops, firing, a custom piece: what the studio offers in Kraków and, when the
 * panel has a price list, each item at its price. The provider points to the business described on every page.
 */
class ServiceStructuredData
{
    /**
     * @param  iterable<array{0: string, 1: int, 2?: ?string, 3?: ?string}>  $prices  [name, price in grosze, unit such as „os.” or „/ l”, page where it is bought]
     */
    public static function for(string $name, string $url, ?string $description = null, iterable $prices = []): string
    {
        $service = Schema::service()
            ->name($name)
            ->url($url)
            ->provider(Schema::localBusiness()->identifier(url('/').'/#business'))
            ->areaServed(Schema::city()->name('Kraków'));

        if (filled($description)) {
            $service->description(trim((string) preg_replace('/\s+/u', ' ', $description)));
        }

        $offers = collect($prices)->map(fn (array $price) => self::offer(...$price))->values()->all();

        if ($offers !== []) {
            $service->hasOfferCatalog(Schema::offerCatalog()->name($name)->itemListElement($offers));
        }

        return $service->toScript();
    }

    private static function offer(string $name, int $price, ?string $unit = null, ?string $url = null): Offer
    {
        $offer = Schema::offer()->itemOffered(Schema::service()->name($name))->price(Money::decimal($price))->priceCurrency('PLN');
        $unit = trim((string) $unit, "/ \t");

        if ($url !== null) {
            $offer->url($url);
        }

        return $unit === ''
            ? $offer
            : $offer->priceSpecification(Schema::unitPriceSpecification()->price(Money::decimal($price))->priceCurrency('PLN')->unitText($unit));
    }
}
