<?php

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Content\Actions\RefreshInstagramFeed;
use App\Modules\Content\Models\InstagramPost;
use App\Modules\Settings\Settings;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Settings $settings): View
    {
        $live = Product::query()->live()->withShelf()
            ->orderBy('sort_order')
            ->get();
        $heroSlug = $settings->get('home_hero_product');

        return view('content::home', [
            // The panel stores the Polish slug; on an English page ->slug reads the English one, so compare the column.
            'hero' => $live->first(fn (Product $product) => $product->getRawOriginal('slug') === $heroSlug) ?? $live->first(),
            'featured' => $live->take(4),
            // Kasia can hide the whole section in the panel (MA-274). Her own posts come first; the feed's posts
            // show only while a feed is set, so clearing the address takes them off at once.
            'instagramPosts' => $settings->get('home_show_instagram_feed') !== false
                ? InstagramPost::forHomePage(filled($settings->get('instagram_feed_url')), RefreshInstagramFeed::POSTS)
                : collect(),
        ]);
    }
}
