<?php

namespace App\Modules\Catalog\Support\Search;

use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Admin\Support\Search\SearchProvider;
use App\Modules\Admin\Support\Search\SearchResult;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Shared\Support\Money;

/**
 * Products in the panel search (MA-308), by name, the name of one piece and the address: the card opens on Enter.
 * In the order of the shop, with the shelf, the price, the pieces and whether it is hidden.
 */
final class ProductSearch implements SearchProvider
{
    public function group(): string
    {
        return 'Produkty';
    }

    public function screen(): string
    {
        return 'admin.products.index';
    }

    public function search(string $term, int $limit): iterable
    {
        $query = Product::query()->with(['category', 'variants', 'media'])->orderBy('sort_order')->orderBy('id');

        return PanelSearch::whereWords($query, $term, ['name', 'piece_name', 'slug'])
            ->limit($limit * 4)
            ->get()
            ->filter(fn (Product $product) => PanelSearch::matches($term, $product->name, $product->piece_name, $product->slug))
            ->take($limit)
            ->map(fn (Product $product) => new SearchResult(
                title: $product->name,
                url: route('admin.products.edit', $product),
                route: 'admin.products.edit',
                detail: $this->detail($product),
                meta: $product->is_published ? ($this->soldOut($product) ? 'Wyprzedany' : null) : 'Ukryty',
                image: $product->getMedia('images')->first()?->getAvailableUrl(['thumb']),
                icon: '·',
            ))
            ->values();
    }

    private function detail(Product $product): string
    {
        $variants = $product->variants->reject(fn (ProductVariant $variant) => $variant->is_hidden);
        $prices = $variants->pluck('price_gross')->filter(fn (?int $price) => $price !== null);
        $counted = $variants->filter(fn (ProductVariant $variant) => $variant->stock !== null);

        return collect([
            $product->category?->name,
            $prices->isEmpty() ? null : ($prices->min() === $prices->max() ? '' : 'od ').Money::format((int) $prices->min()),
            $counted->isEmpty() ? null : $counted->sum('stock').' szt.',
        ])->filter()->join(' · ');
    }

    private function soldOut(Product $product): bool
    {
        $variants = $product->variants->reject(fn (ProductVariant $variant) => $variant->is_hidden);

        return $variants->isNotEmpty() && $variants->every(fn (ProductVariant $variant) => ! $variant->isInStock());
    }
}
