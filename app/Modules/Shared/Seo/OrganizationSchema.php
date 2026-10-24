<?php

namespace App\Modules\Shared\Seo;

use App\Modules\Settings\Settings;
use App\Modules\Shared\Contracts\PriceRange;

/**
 * Organization / LocalBusiness on every page.
 *
 * No street address: only the city and the area served — the studio address
 * never reaches the page or the markup. Empty settings are left out.
 */
final class OrganizationSchema
{
    public function __construct(
        private readonly Settings $settings,
        private readonly PriceRange $priceRange,
    ) {}

    public static function id(): string
    {
        return url('/').'#organization';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $instagram = $this->settings->get('contact.instagram');

        $sameAs = array_values(array_filter([
            $instagram ? 'https://www.instagram.com/'.$instagram.'/' : null,
            $this->settings->get('contact.facebook_url'),
            $this->settings->get('contact.google_business_url'),
        ]));

        return array_filter([
            '@type' => ['Organization', 'LocalBusiness'],
            '@id' => self::id(),
            'name' => Seo::BRAND,
            'url' => url('/'),
            'telephone' => $this->settings->get('contact.phone'),
            'email' => $this->settings->get('contact.email'),
            'founder' => ['@type' => 'Person', 'name' => 'Katarzyna Samborska'],
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Kraków', 'addressCountry' => 'PL'],
            'areaServed' => ['@type' => 'Country', 'name' => 'Polska'],
            'priceRange' => $this->priceRangeLabel(),
            'sameAs' => $sameAs,
        ]);
    }

    private function priceRangeLabel(): ?string
    {
        $bounds = $this->priceRange->bounds();

        if ($bounds === null) {
            return null;
        }

        [$min, $max] = array_map(fn (int $grosze) => intdiv($grosze, 100), $bounds);

        return $min === $max ? "{$min} PLN" : "{$min}–{$max} PLN";
    }
}
