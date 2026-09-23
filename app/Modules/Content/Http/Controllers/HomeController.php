<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Content\Actions\RefreshInstagramFeed;
use App\Modules\Content\Models\InstagramPost;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Settings $settings): View
    {
        $live = Product::query()->live()
            ->with(['category', 'variants', 'media'])
            ->when(Locales::current() !== Locales::default(), fn ($query) => $query->with(['category.translations', 'variants.translations']))
            ->orderBy('sort_order')
            ->get();
        $heroSlug = $settings->get('home_hero_product');

        return view('content::home', [
            // The panel stores the Polish slug; on an English page ->slug reads the English one, so compare the column.
            'hero' => $live->first(fn (Product $product) => $product->getRawOriginal('slug') === $heroSlug) ?? $live->first(),
            'featured' => $live->take(4),
            // Posts show only while a feed is set; clearing the address hides them at once.
            'instagramPosts' => filled($settings->get('instagram_feed_url'))
                ? InstagramPost::query()->latest('posted_at')->take(RefreshInstagramFeed::POSTS)->get()
                : collect(),
        ]);
    }
}
