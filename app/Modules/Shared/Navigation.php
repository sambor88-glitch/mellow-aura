<?php

namespace App\Modules\Shared;

use Illuminate\Support\Facades\Route;

/**
 * Links in the header and footer, in the order of the prototype.
 *
 * A link shows up only once its route exists, so the site never links to a
 * page that is not built yet — modules arrive week by week.
 */
final class Navigation
{
    /**
     * @return array<int, array{label: string, url: string, route: string}>
     */
    public static function header(): array
    {
        return self::resolve([
            ['shop.index', 'Sklep'],
            ['gifts.index', 'Prezenty'],
            ['workshops.index', 'Warsztaty'],
            ['about.show', 'O mnie'],
            ['journal.index', 'Dziennik'],
            ['contact.show', 'Kontakt'],
        ]);
    }

    /**
     * @return array<int, array{label: string, url: string, route: string}>
     */
    public static function footerShop(): array
    {
        return self::resolve([
            ['shop.index', 'Wszystkie produkty'],
            ['mug.show', 'Kubek z napisem'],
            ['gifts.index', 'Szukam prezentu'],
            ['bundles.index', 'Zestawy prezentowe'],
            ['shop.category', 'Vouchery na warsztaty', ['category' => 'vouchery']],
            ['faq.show', 'Wysyłka i zwroty'],
        ]);
    }

    /**
     * @return array<int, array{label: string, url: string, route: string}>
     */
    public static function footerStudio(): array
    {
        return self::resolve([
            ['workshops.index', 'Warsztaty i cennik'],
            ['custom-orders.show', 'Zamówienia indywidualne'],
            ['scarf.show', 'Z Twojej apaszki'],
            ['imprint.show', 'Odcisk Twojej rośliny'],
            ['b2b.show', 'Dla kawiarni i restauracji'],
            ['firing.show', 'Wypały na zlecenie'],
        ]);
    }

    /**
     * URL of a route when it exists, null otherwise — for single links in views.
     */
    public static function url(string $route, array $parameters = []): ?string
    {
        return Route::has($route) ? route($route, $parameters) : null;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2?: array<string, mixed>}>  $links
     * @return array<int, array{label: string, url: string, route: string}>
     */
    private static function resolve(array $links): array
    {
        $resolved = [];

        foreach ($links as $link) {
            $url = self::url($link[0], $link[2] ?? []);

            if ($url !== null) {
                $resolved[] = ['label' => $link[1], 'url' => $url, 'route' => $link[0]];
            }
        }

        return $resolved;
    }
}
