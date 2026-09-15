<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductStructuredData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __invoke(Request $request, Product $product): View
    {
        abort_unless($product->is_published, 404);

        $product->load(['category', 'variants' => fn ($query) => $query->orderBy('id'), 'media']);

        $variant = $product->variants->firstWhere('id', (int) $request->query('wariant')) ?? $product->variants->first();

        abort_if($variant === null, 404);

        $variant->setRelation('product', $product);

        // Same category first, then the same group (ceramics, crafts, workshops).
        $related = Product::query()->live()
            ->whereKeyNot($product->getKey())
            ->with(['category', 'variants', 'media'])
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
            'structuredData' => ProductStructuredData::for($product),
        ]);
    }
}
