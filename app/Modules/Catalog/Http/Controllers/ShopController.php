<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Enums\ShopSort;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Shared\Seo\Breadcrumbs;
use App\Modules\Shared\Seo\Seo;
use Collator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    private const TITLE = 'Sklep — ceramika i rękodzieło handmade | MellowAura';

    private const DESCRIPTION = 'Kubki, talerze, wazony, patery, kadzielnice oraz jedwabne scrunchies i opaski. Każda sztuka jedna, wysyłka w 3–5 dni, BLIK.';

    private const SEARCH_MAX_LENGTH = 80;

    public function index(Request $request): View
    {
        return $this->render($request, null);
    }

    public function category(Request $request, Category $category): View
    {
        return $this->render($request, $category);
    }

    private function render(Request $request, ?Category $category): View
    {
        $search = Str::limit(trim((string) $request->query('q', '')), self::SEARCH_MAX_LENGTH, '');
        $sort = ShopSort::tryFrom((string) $request->query('sort')) ?? ShopSort::Recommended;

        $products = $this->sorted(
            Product::query()
                ->available()
                ->when($category, fn (Builder $query) => $query->where('category_id', $category->id))
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                    $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
                    $query->where('name', 'like', $like)->orWhere('description', 'like', $like);
                }))
                ->with(['category', 'variants', 'media'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            $sort,
        );

        $categories = Category::query()
            ->withCount(['products as available_count' => fn (Builder $query) => $query->available()])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Category $item) => $item->available_count > 0 || $item->is($category));

        $breadcrumbs = Breadcrumbs::home()->add('Sklep', $category ? route('shop.index') : null);
        $breadcrumbs = $category ? $breadcrumbs->add($category->name) : $breadcrumbs;

        $seo = $category
            ? Seo::make(
                $category->seo_title ?: Seo::titleFor($category->name, 'rękodzieło z Krakowa'),
                $category->seo_description ?: (string) $category->intro,
                route('shop.category', $category),
            )
            : Seo::make(self::TITLE, self::DESCRIPTION, route('shop.index'));

        $seo = $seo->withSchema($breadcrumbs->schema());

        return view('catalog::shop', [
            'seo' => $search === '' ? $seo : $seo->noindex(),
            'breadcrumbs' => $breadcrumbs,
            'category' => $category,
            'categories' => $categories,
            'products' => $products,
            'totalAvailable' => $categories->sum('available_count'),
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    /**
     * Sorted in PHP: the shelf holds tens of products, and Polish collation
     * then works the same on MySQL and on SQLite in tests.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function sorted(Collection $products, ShopSort $sort): Collection
    {
        return match ($sort) {
            ShopSort::Recommended => $products,
            ShopSort::PriceAsc => $products->sortBy(fn (Product $product) => $product->priceFrom())->values(),
            ShopSort::PriceDesc => $products->sortByDesc(fn (Product $product) => $product->priceFrom())->values(),
            ShopSort::Name => $products->sort($this->nameComparator())->values(),
        };
    }

    private function nameComparator(): \Closure
    {
        $collator = class_exists(Collator::class) ? new Collator('pl_PL') : null;

        return fn (Product $a, Product $b) => $collator
            ? $collator->compare($a->name, $b->name)
            : strcmp(mb_strtolower($a->name), mb_strtolower($b->name));
    }
}
