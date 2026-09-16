<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * The shop in sitemap.xml: categories with something to buy and every published product with its photos,
 * which also helps the photos show up in image search. A sold unique piece stays, like its page does.
 */
class CatalogSitemap
{
    public function __invoke(Sitemap $sitemap): void
    {
        $sitemap->add(Url::create(route('shop.index')));

        $liveCategoryIds = Product::query()->live()->distinct()->pluck('category_id');

        Category::query()->whereIn('id', $liveCategoryIds)->orderBy('sort_order')->get()
            ->each(fn (Category $category) => $sitemap->add(Url::create(route('shop.category', $category))));

        Product::query()->where('is_published', true)->whereHas('variants')->with(['variants', 'media'])->orderBy('sort_order')->get()
            ->each(function (Product $product) use ($sitemap) {
                $changed = collect([$product->updated_at])
                    ->merge($product->variants->pluck('updated_at'))
                    ->merge($product->media->pluck('updated_at'))
                    ->filter()
                    ->max();

                $url = Url::create(route('product.show', $product));

                if ($changed !== null) {
                    $url->setLastModificationDate($changed);
                }

                foreach ($product->getMedia('images') as $photo) {
                    $url->addImage($photo->getUrl(), caption: (string) $photo->getCustomProperty('alt', ''), title: $product->name);
                }

                $sitemap->add($url);
            });
    }
}
