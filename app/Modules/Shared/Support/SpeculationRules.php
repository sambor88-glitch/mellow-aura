<?php

namespace App\Modules\Shared\Support;

use App\Modules\Localization\Support\Locales;
use Illuminate\Routing\Router;

/**
 * The rules a Chromium browser follows to render the next page before the click (Speculation Rules, MA-237).
 * A list of what may be rendered, never „everything but”: a new page stays out until someone adds it here.
 *
 * Only pages that read and change nothing qualify. Out on purpose: the cart and checkout (/zamowienie, Przelewy24),
 * the panel, the page of a bought piece (/k/, a throttle counts every request), anything with a query string,
 * the language switch (it writes a cookie on click) and other sites. A prerender leaves the session as it was
 * (Shared\Http\Middleware\StartSession) and Analytics waits for the page to be shown.
 */
class SpeculationRules
{
    /**
     * Route names whose pages may be prerendered, together with their English twins. A name without a route
     * (a module that is off) is skipped.
     *
     * @var list<string>
     */
    public const PAGES = [
        'home',
        'shop.index',
        'shop.category',
        'product.show',
        'mug.index',
        'bundles.index',
        'gifts.index',
        'vouchers.index',
        'workshops.index',
        'firing.index',
        'custom-orders.index',
        'content.about',
        'content.studio',
        'content.contact',
        'content.faq',
        'content.b2b',
        'content.imprint',
        'content.scarf',
        'content.terms',
        'content.privacy',
    ];

    /**
     * Links that stay plain links even on a page from the list.
     */
    private const SKIP = '[href*="?"], [download], [target], [data-locale-choice], [data-no-prerender]';

    public function __construct(private readonly Router $router) {}

    /**
     * @return list<string> URL patterns, e.g. `/produkt/*`
     */
    public function patterns(): array
    {
        $patterns = [];

        foreach (self::PAGES as $name) {
            foreach (Locales::enabled() as $locale) {
                $routeName = Locales::nameFor($name, $locale);
                $route = $routeName === null ? null : $this->router->getRoutes()->getByName($routeName);

                if ($route !== null) {
                    $patterns[] = '/'.ltrim((string) preg_replace('/\{[^}]+\}/', '*', $route->uri()), '/');
                }
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * The body of <script type="speculationrules">. `moderate`: on a computer after a short hover or on mouse down,
     * on a phone when a link stays in view for a moment — the server does not get a request for every link on the page.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prerender' => [[
                'where' => ['and' => [
                    ['href_matches' => $this->patterns()],
                    ['not' => ['selector_matches' => self::SKIP]],
                ]],
                'eagerness' => 'moderate',
            ]],
        ];
    }
}
