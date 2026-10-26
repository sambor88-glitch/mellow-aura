<?php

namespace App\Modules\Shared\Support;

use App\Modules\Settings\Settings;
use Closure;
use Spatie\SchemaOrg\Schema;

/**
 * JSON-LD describing the studio, on every page. Only the city goes in — the studio's street
 * address never does — and empty settings are left out. There is no logo yet: Google needs
 * an image file of at least 112 px, and the site has only a text wordmark.
 */
class BusinessStructuredData
{
    /** @var (Closure(): (array{int, int}|null))|null */
    private static ?Closure $priceRange = null;

    /**
     * Lets the catalogue report its lowest and highest shelf price in grosze without Shared depending on it.
     *
     * @param  Closure(): (array{int, int}|null)  $resolver
     */
    public static function priceRangeUsing(Closure $resolver): void
    {
        self::$priceRange = $resolver;
    }

    public static function for(Settings $settings): string
    {
        $home = url('/');
        $phone = preg_replace('/\D+/', '', (string) $settings->get('contact_phone'));
        $instagram = $settings->get('instagram_handle');

        $profiles = array_values(array_filter([
            $instagram ? 'https://www.instagram.com/'.ltrim($instagram, '@').'/' : null,
            $settings->get('facebook_url'),
            $settings->get('google_business_profile_url'),
        ]));

        return Schema::localBusiness()
            ->identifier($home.'/#business')
            ->name(Seo::BRAND)
            ->description('Pracownia ceramiki i jedwabnego rękodzieła Kasi Samborskiej.')
            ->url($home)
            ->founder(Schema::person()->name('Katarzyna Samborska'))
            ->address(Schema::postalAddress()->addressLocality('Kraków')->addressCountry('PL'))
            ->areaServed('Kraków')
            ->telephone($phone ? '+'.$phone : null)
            ->email($settings->get('contact_email'))
            ->priceRange(self::priceRange())
            ->sameAs($profiles ?: null)
            ->toScript();
    }

    /**
     * Whole złoty rounded outwards, e.g. "59–499 zł".
     */
    private static function priceRange(): ?string
    {
        $range = self::$priceRange ? (self::$priceRange)() : null;

        if ($range === null) {
            return null;
        }

        $lowest = intdiv($range[0], 100);
        $highest = intdiv($range[1] + 99, 100);

        return $lowest === $highest ? $lowest.' zł' : $lowest.'–'.$highest.' zł';
    }
}
