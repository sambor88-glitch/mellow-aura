<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Seo\ProductSchema;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Seo\Breadcrumbs;
use App\Modules\Shared\Seo\Seo;
use App\Modules\Shared\Seo\SeoText;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;

class ProductController extends Controller
{
    private const RELATED_COUNT = 4;

    public function show(Request $request, Product $product, ProductSchema $schema, Settings $settings): View
    {
        // A draft is not on the site at all; a sold-out piece keeps its page.
        abort_unless($product->is_published, 404);

        $product->load(['category', 'variants', 'media']);

        $selected = $this->selectedVariant($product, (int) $request->query('variant'));
        $description = SeoText::description($product->seo_description ?: $product->description);

        $breadcrumbs = Breadcrumbs::home()->add('Sklep', route('shop.index'));
        $breadcrumbs = $product->category
            ? $breadcrumbs->add($product->category->name, route('shop.category', $product->category))
            : $breadcrumbs;
        $breadcrumbs = $breadcrumbs->add($product->name);

        $seo = new Seo(
            $this->titleOptions($product),
            $description,
            path: route('product.show', $product, false),
            type: 'product',
            schema: [$schema->toArray($product, $description), $breadcrumbs->schema()],
        );

        return view('catalog::product', [
            'seo' => $seo,
            'breadcrumbs' => $breadcrumbs,
            'product' => $product,
            'selected' => $selected,
            'related' => $this->related($product),
            'freeShippingFrom' => $settings->get('shipping.free_threshold'),
            'dispatchTime' => $settings->get('shipping.dispatch_time'),
        ]);
    }

    /**
     * "{name} — ceramika handmade | MellowAura", silk "— rękodzieło z jedwabiu",
     * vouchers only the name and the brand (spec, section 2). The brand drops first.
     *
     * @return list<string>
     */
    private function titleOptions(Product $product): array
    {
        $tagline = $product->category ? $product->category->productTitleTagline() : 'ceramika handmade';

        return $tagline === null
            ? ["{$product->name} | MellowAura", $product->name]
            : ["{$product->name} — {$tagline} | MellowAura", "{$product->name} — {$tagline}", "{$product->name} | MellowAura", $product->name];
    }

    /**
     * ?variant= from a Google Shopping link, else the first one in stock.
     */
    private function selectedVariant(Product $product, int $requested): ?ProductVariant
    {
        return $product->variants->firstWhere('id', $requested)
            ?? $product->variants->first(fn (ProductVariant $variant) => $variant->stock > 0)
            ?? $product->variants->first();
    }

    /**
     * "Z tej samej półki": the same category first, then the rest of the shelf.
     */
    private function related(Product $product): Collection
    {
        $base = fn () => Product::query()
            ->available()
            ->whereKeyNot($product->getKey())
            ->with(['category', 'variants', 'media'])
            ->orderBy('sort_order')
            ->orderBy('id');

        $sameCategory = $base()
            ->when($product->category_id, fn (Builder $query) => $query->where('category_id', $product->category_id))
            ->limit(self::RELATED_COUNT)
            ->get();

        if ($sameCategory->count() >= self::RELATED_COUNT) {
            return $sameCategory;
        }

        return $sameCategory->concat(
            $base()->whereKeyNot($sameCategory->modelKeys())->limit(self::RELATED_COUNT - $sameCategory->count())->get(),
        );
    }
}
