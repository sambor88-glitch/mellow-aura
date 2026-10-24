<?php

namespace App\Modules\Shared\Seo;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Settings;

/**
 * LocalBusiness JSON-LD for every page (specification, point 3).
 *
 * Contact data and profiles come from the panel; an empty field is left out.
 * No street address: the studio address never goes on the site or into markup.
 * No AggregateRating: Google shows no stars for reviews of the business itself.
 */
final class BusinessSchema
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $url = rtrim((string) config('app.url'), '/').'/';

        $instagram = ltrim((string) $this->settings->get('contact.instagram', ''), '@');
        $phone = preg_replace('/[^\d+]/', '', (string) $this->settings->get('contact.phone', ''));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            '@id' => $url.'#pracownia',
            'name' => 'MellowAura',
            'description' => 'Pracownia ceramiki i jedwabnego rękodzieła Kasi Samborskiej.',
            'url' => $url,
            'telephone' => $phone,
            'email' => $this->settings->get('contact.email'),
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Kraków',
                'addressCountry' => 'PL',
            ],
            'areaServed' => 'Kraków',
            'founder' => ['@type' => 'Person', 'name' => 'Katarzyna Samborska'],
            'priceRange' => $this->priceRange(),
            'sameAs' => array_values(array_filter([
                $instagram !== '' ? "https://www.instagram.com/{$instagram}/" : null,
                $this->settings->get('contact.facebook_url'),
                $this->settings->get('contact.google_business_url'),
            ])),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * "59–499 zł" from the published offer, in whole złoty.
     */
    private function priceRange(): ?string
    {
        $prices = ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->published())
            ->selectRaw('min(price_gross) as low, max(price_gross) as high')
            ->first();

        if ($prices?->low === null) {
            return null;
        }

        $low = intdiv((int) $prices->low, 100);
        $high = (int) ceil($prices->high / 100);

        return $low === $high ? "{$low} zł" : "{$low}–{$high} zł";
    }
}
