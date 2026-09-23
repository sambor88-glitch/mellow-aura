<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Localization\Support\Locales;
use App\Modules\Shared\Support\BreadcrumbStructuredData;
use Collator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopController extends Controller
{
    /** @var array<string, string> sort => its label in catalog::shop */
    private const SORTS = [
        '' => 'sort_featured',
        'price_asc' => 'sort_price_asc',
        'price_desc' => 'sort_price_desc',
        'name' => 'sort_name',
    ];

    public function __invoke(Request $request, ?Category $category = null): View
    {
        $search = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', '');
        $sort = array_key_exists($sort, self::SORTS) ? $sort : '';

        $live = Product::query()->live()
            ->with(['category', 'variants', 'media'])
            // Only where they are read: a Polish page reads the Polish columns.
            ->when(Locales::current() !== Locales::default(), fn ($query) => $query->with(['category.translations', 'variants.translations']))
            ->orderBy('sort_order')
            ->get();

        $products = $this->sorted(
            $live->filter(fn (Product $product) => $this->inCategory($product, $category) && $this->matches($product, $search))->values(),
            $sort,
        );

        // Filters are query parameters; the canonical address stays the bare shop or category URL.
        $baseUrl = $category ? route('shop.category', $category) : route('shop.index');
        $withQuery = fn (string $url, array $query) => $url.(($query = array_filter($query)) ? '?'.http_build_query($query) : '');

        $chips = Category::query()->translatedInto()->orderBy('sort_order')->get()
            ->map(fn (Category $item) => [$item, $live->filter(fn (Product $product) => $this->inCategory($product, $item))->count()])
            ->filter(fn (array $pair) => $pair[1] > 0 || $pair[0]->is($category))
            ->map(fn (array $pair) => [
                'label' => $pair[0]->name.' ('.$pair[1].')',
                'url' => $withQuery(route('shop.category', $pair[0]), ['q' => $search, 'sort' => $sort]),
                'active' => $pair[0]->is($category),
            ])
            ->prepend([
                'label' => __('catalog::shop.all', ['count' => $live->count()]),
                'url' => $withQuery(route('shop.index'), ['q' => $search, 'sort' => $sort]),
                'active' => $category === null,
            ])
            ->values();

        $sorts = collect(self::SORTS)->map(fn (string $label, string $key) => [
            'label' => __('catalog::shop.'.$label),
            'url' => $withQuery($baseUrl, ['q' => $search, 'sort' => $key]),
            'active' => $key === $sort,
        ])->values();

        return view('catalog::shop.index', [
            'category' => $category,
            'products' => $products,
            'liveCount' => $live->count(),
            'chips' => $chips,
            'sorts' => $sorts,
            'search' => $search,
            'sort' => $sort,
            'baseUrl' => $baseUrl,
            'clearSearchUrl' => $withQuery($baseUrl, ['sort' => $sort]),
            // The same trail the page shows as links.
            'structuredData' => $category ? BreadcrumbStructuredData::for([
                [__('catalog::shop.home'), route('home')],
                [__('catalog::shop.products'), route('shop.index')],
                [$category->name, $baseUrl],
            ]) : null,
        ]);
    }

    private function inCategory(Product $product, ?Category $category): bool
    {
        return $category === null || $product->category_id === $category->id;
    }

    private function matches(Product $product, string $search): bool
    {
        return $search === '' || Str::contains($product->name.' '.$product->description, $search, ignoreCase: true);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function sorted(Collection $products, string $sort): Collection
    {
        $lowestPrice = fn (Product $product) => $product->variants->min('price_gross');

        return match ($sort) {
            'price_asc' => $products->sortBy($lowestPrice)->values(),
            'price_desc' => $products->sortByDesc($lowestPrice)->values(),
            'name' => $products->sort(fn (Product $a, Product $b) => $this->compareNames($a->name, $b->name))->values(),
            default => $products,
        };
    }

    private function compareNames(string $a, string $b): int
    {
        // The alphabet of the page: ł after l in Polish, plain A–Z in English. One per language, since a process
        // (tests, Octane) serves pages in both.
        static $collators = [];
        $collator = $collators[Locales::current()] ??= class_exists(Collator::class) ? new Collator(Locales::ogLocale()) : false;

        return $collator ? (int) $collator->compare($a, $b) : strcmp($a, $b);
    }
}
