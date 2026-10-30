<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductStructuredData;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __invoke(Request $request, Product $product, Settings $settings): View|RedirectResponse
    {
        // A hidden product may still be in Google or in someone's link: its shelf is the closest thing (specification, point 1).
        // On an English page only when the category is written in English, or its address would have no slug.
        if (! $product->is_published) {
            return $product->category->hasTranslation(Locales::current())
                ? to_route('shop.category', $product->category, 301)
                : to_route('shop.index', status: 301);
        }

        $product->loadShelf();

        // Written in English but not priced in euro yet: the English shop is the closest thing, for now.
        if ($product->variants->isEmpty() && Locales::current() !== Locales::default()) {
            return to_route('shop.index');
        }

        $variant = $product->variants->firstWhere('id', (int) $request->query('wariant')) ?? $product->variants->first();

        abort_if($variant === null, 404);

        $variant->setRelation('product', $product);

        // Same category first, then the same group (ceramics, crafts, workshops).
        $related = Product::query()->live()->withShelf()
            ->whereKeyNot($product->getKey())
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Product $other) => $other->category->group === $product->category->group)
            ->sortBy(fn (Product $other) => $other->category_id === $product->category_id ? 0 : 1)
            ->take(4)
            ->values();

        return view('catalog::product.show', [
            'product' => $product,
            'variant' => $variant,
            'related' => $related,
            'structuredData' => ProductStructuredData::for($product, $settings),
        ]);
    }
}
