<?php

namespace App\Modules\Content\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Seo\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

class HomeController extends Controller
{
    private const TITLE = 'MellowAura — ceramika i rękodzieło z jedwabiu, Kraków';

    private const DESCRIPTION = 'Ręcznie formowana ceramika i jedwabne dodatki z drugiego obiegu. Kubki z wbijanym napisem, warsztaty w kameralnej pracowni w Krakowie. Płatność BLIK.';

    private const FEATURED_COUNT = 4;

    /**
     * Photos of the studio strip, from zdjecia/ — all of them Kasia's.
     */
    private const STRIP_PHOTOS = [
        ['kasia-talerz-zloto.webp', '1 / 1'],
        ['misa-laguna.webp', '3 / 4'],
        ['kubek-nie-powinnam.webp', '1 / 1'],
        ['wazon-rzezbiony.webp', '1 / 1'],
        ['podstawki-muszle-roz.webp', '3 / 4'],
        ['talerz-niebieski-odcisk.webp', '1 / 1'],
        ['patera-czarna-kolce.webp', '1 / 1'],
        ['zestaw-flatlay.webp', '3 / 4'],
    ];

    public function __invoke(Settings $settings): View
    {
        $shelf = Product::query()
            ->available()
            ->with(['category', 'variants', 'media'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Chosen in the panel; when it is sold out or hidden, the first product on the shelf.
        $hero = $shelf->firstWhere('slug', $settings->get('home.hero_product')) ?? $shelf->first();

        return view('content::home', [
            'seo' => Seo::make(self::TITLE, self::DESCRIPTION, route('home')),
            'hero' => $hero,
            'heroBadge' => $settings->get('home.hero_badge'),
            'featured' => $shelf->take(self::FEATURED_COUNT),
            'dispatchTime' => $settings->get('shipping.dispatch_time'),
            'kasiaHeading' => $settings->get('texts.home_kasia_heading'),
            'kasiaLead' => $settings->get('texts.home_kasia_lead'),
            'stripPhotos' => self::STRIP_PHOTOS,
        ]);
    }
}
