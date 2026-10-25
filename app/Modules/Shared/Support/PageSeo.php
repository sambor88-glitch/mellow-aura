<?php

namespace App\Modules\Shared\Support;

use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * Titles and descriptions for Google that Kasia sets in the panel („Google”) for the fixed pages of the site.
 * They live in the setting `seo_pages`, keyed by route name and language. An empty field means the page composes its
 * own title or description, as it always has.
 */
final class PageSeo
{
    public const SETTING = 'seo_pages';

    /** Fixed pages by route name, in the order of the address map in the specification. */
    public const PAGES = [
        'home' => 'Strona główna',
        'shop.index' => 'Sklep',
        'mug.index' => 'Kubek z napisem',
        'gifts.index' => 'Szukam prezentu',
        'bundles.index' => 'Zestawy prezentowe',
        'vouchers.index' => 'Voucher na warsztaty',
        'content.scarf' => 'Z Twojej apaszki',
        'content.imprint' => 'Odcisk Twojej rośliny',
        'workshops.index' => 'Warsztaty',
        'custom-orders.index' => 'Zamówienia indywidualne',
        'firing.index' => 'Wypał ceramiki',
        'content.b2b' => 'Ceramika dla gastronomii',
        'content.about' => 'O mnie',
        'content.studio' => 'Pracownia',
        'content.faq' => 'Wysyłka i pielęgnacja',
        'content.contact' => 'Kontakt',
    ];

    public function __construct(private Settings $settings) {}

    /**
     * The title and description from the panel for the page on $routeName, null where the field is empty.
     *
     * @return array{title: ?string, description: ?string}
     */
    public function custom(?string $routeName, ?string $locale = null): array
    {
        $page = Locales::baseName($routeName);
        $saved = $page !== null && array_key_exists($page, self::PAGES)
            ? (array) (((array) $this->settings->get(self::SETTING, []))[$page][$locale ?? Locales::current()] ?? [])
            : [];

        return [
            'title' => filled($saved['title'] ?? null) ? (string) $saved['title'] : null,
            'description' => filled($saved['description'] ?? null) ? (string) $saved['description'] : null,
        ];
    }

    /**
     * Keeps what a fixed page composes on its own, so the panel can show it as a hint in an empty field.
     * Written only when it changes.
     */
    public function rememberComposed(?string $routeName, string $title, ?string $description): void
    {
        $page = Locales::baseName($routeName);

        if ($page === null || ! array_key_exists($page, self::PAGES)) {
            return;
        }

        $key = self::composedKey($page, Locales::current());
        $composed = ['title' => $title, 'description' => $description];

        if (Cache::get($key) !== $composed) {
            Cache::forever($key, $composed);
        }
    }

    /**
     * @return array{title: string, description: ?string}|null
     */
    public function composed(string $page, string $locale): ?array
    {
        return Cache::get(self::composedKey($page, $locale));
    }

    /**
     * The page in a form field name: validation reads dots as nesting, so „shop.index” travels as „shop__index”.
     */
    public static function fieldKey(string $page): string
    {
        return str_replace('.', '__', $page);
    }

    public static function pageFromFieldKey(string $key): string
    {
        return str_replace('__', '.', $key);
    }

    private static function composedKey(string $page, string $locale): string
    {
        return 'seo.composed.'.$locale.'.'.$page;
    }
}
