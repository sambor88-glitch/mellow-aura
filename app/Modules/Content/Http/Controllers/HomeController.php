<?php

namespace App\Modules\Content\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Seo\Seo;
use Illuminate\Contracts\View\View;

class HomeController
{
    public function __invoke(Settings $settings): View
    {
        $live = Product::query()
            ->published()
            ->inStock()
            ->with(['category', 'variants', 'media'])
            ->orderBy('sort_order');

        $heroSlug = $settings->get('home.hero_product_slug');

        return view('content::home', [
            'seo' => new Seo(
                title: 'MellowAura — ceramika i rękodzieło z jedwabiu, Kraków',
                description: 'Ręcznie formowana ceramika i jedwabne dodatki z drugiego obiegu. Kubki z wbijanym napisem, warsztaty w kameralnej pracowni w Krakowie. Płatność BLIK.',
                path: '/',
            ),
            'featured' => (clone $live)->limit(4)->get(),
            'hero' => $heroSlug ? (clone $live)->where('slug', $heroSlug)->first() : null,
            'heroBadge' => $settings->get('home.hero_badge'),
            'dispatchTime' => $settings->get('shipping.dispatch_time'),
            'kasiaHeading' => $settings->get('texts.home_kasia_heading'),
            'kasiaBody' => $settings->get('texts.home_kasia_body'),
            'scarfPriceFrom' => $settings->get('services.scarf_price_from'),
            'imprintPriceFrom' => $settings->get('services.imprint_price_from'),
        ]);
    }
}
