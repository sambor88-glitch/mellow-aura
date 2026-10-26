<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Shared\Support\SitemapPages;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * The shop in sitemap.xml: categories with something to buy and every published product with its photos,
 * which also helps the photos show up in image search. A sold unique piece stays, like its page does.
 * Each in every language it exists in (SitemapPages::versions).
 */
class CatalogSitemap
{
    public function __invoke(Sitemap $sitemap): void
    {
        $sitemap->add(SitemapPages::versions('shop.index'));

        $liveCategoryIds = Product::query()->live()->distinct()->pluck('category_id');

        Category::query()->whereIn('id', $liveCategoryIds)->orderBy('sort_order')->get()
            ->each(fn (Category $category) => $sitemap->add(SitemapPages::versions('shop.category', [$category])));

        Product::query()->where('is_published', true)->whereHas('variants')->with(['variants', 'media'])->orderBy('sort_order')->get()
            ->each(function (Product $product) use ($sitemap) {
                $changed = collect([$product->updated_at])
                    ->merge($product->variants->pluck('updated_at'))
                    ->merge($product->media->pluck('updated_at'))
                    ->filter()
                    ->max();

                $sitemap->add(SitemapPages::versions('product.show', [$product], function (Url $url) use ($product, $changed) {
                    if ($changed !== null) {
                        $url->setLastModificationDate($changed);
                    }

                    // The product's name in the entry's language; the description of the photo is Kasia's own.
                    foreach ($product->getMedia('images') as $photo) {
                        $url->addImage($photo->getUrl(), caption: (string) $photo->getCustomProperty('alt', ''), title: $product->name);
                    }
                }));
            });
    }
}
