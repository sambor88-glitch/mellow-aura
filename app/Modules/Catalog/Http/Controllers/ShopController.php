<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\CategoryTree;
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

        $tree = CategoryTree::load();
        // The bound model has its parent from the tree, so breadcrumbs and the language check never query again.
        $category = $category ? $tree->get($category->id) : null;

        // A shelf under one not written in this language has no page in it: its breadcrumbs would show Polish.
        abort_if($category !== null && ! $category->availableIn(Locales::current()), 404);

        $live = Product::query()->live()->withShelf()->with('alsoInCategories:id')
            ->orderBy('sort_order')
            ->get();
        $counts = $tree->counts($live);

        // The shelf and everything under it; a product counts by its main shelf and by „Pokaż też w”.
        $shelves = $category ? $tree->subtreeIds($category) : null;
        $products = $this->sorted(
            $live->filter(fn (Product $product) => ($shelves === null || array_intersect($product->shelfIds(), $shelves) !== []) && $this->matches($product, $search))->values(),
            $sort,
        );

        // Filters are query parameters; the canonical address stays the bare shop or category URL.
        $baseUrl = $category ? route('shop.category', $category) : route('shop.index');
        $withQuery = fn (string $url, array $query) => $url.(($query = array_filter($query)) ? '?'.http_build_query($query) : '');
        $path = $category ? $tree->path($category) : collect();
        // „Kubki z własnym napisem” opens with the mug configurator, counted as one more thing on the shelf.
        $configurator = fn (?Category $item) => $item?->getRawOriginal('slug') === Category::PERSONALISED_MUGS && Locales::has('mug.index', Locales::current()) ? 1 : 0;
        $mugConfigurator = $configurator($category) === 1;

        // One row of chips per level of the open branch: Glina and Szycie, then the shelves of the one open, then
        // the shelves under the open one of those. A shelf with nothing on it stays out, unless it is open.
        $chip = fn (Category $item) => [
            'label' => $item->name,
            'count' => ($counts[$item->id] ?? 0) + $configurator($item),
            'url' => $withQuery(route('shop.category', $item), ['q' => $search, 'sort' => $sort]),
            'chosen' => $path->contains('id', $item->id),
            'current' => $item->is($category),
        ];
        $row = fn ($items) => $items
            ->filter(fn (Category $item) => $item->availableIn(Locales::current()))
            ->map($chip)
            ->filter(fn (array $item) => $item['count'] > 0 || $item['chosen'])
            ->values();

        $chipRows = collect([$row($tree->shopTop())->prepend([
            'label' => __('catalog::shop.all'),
            'count' => $live->count(),
            'url' => $withQuery(route('shop.index'), ['q' => $search, 'sort' => $sort]),
            'chosen' => $category === null,
            'current' => $category === null,
        ])]);

        foreach ($path->take(Category::MAX_DEPTH - 1) as $open) {
            $chipRows->push($row($tree->children($open)));
        }

        $sorts = collect(self::SORTS)->map(fn (string $label, string $key) => [
            'label' => __('catalog::shop.'.$label),
            'url' => $withQuery($baseUrl, ['q' => $search, 'sort' => $key]),
            'active' => $key === $sort,
        ])->values();

        // The trail the page shows: the shop, every shelf above this one, this one.
        $trail = $path->map(fn (Category $step) => [$step->name, route('shop.category', $step)])->values()->all();

        return view('catalog::shop.index', [
            'category' => $category,
            'products' => $products,
            'liveCount' => $live->count(),
            'chipRows' => $chipRows->filter(fn ($chips) => $chips->isNotEmpty())->values(),
            'sorts' => $sorts,
            'search' => $search,
            'sort' => $sort,
            'baseUrl' => $baseUrl,
            'clearSearchUrl' => $withQuery($baseUrl, ['sort' => $sort]),
            'trail' => $trail,
            'mugConfigurator' => $mugConfigurator,
            // A search, or a shelf with nothing on it yet, stays out of Google.
            'noindex' => $search !== '' || ($category !== null && ($counts[$category->id] ?? 0) === 0 && ! $mugConfigurator),
            'structuredData' => $category ? BreadcrumbStructuredData::for([
                [__('catalog::shop.home'), route('home')],
                [__('catalog::shop.products'), route('shop.index')],
                ...$trail,
            ]) : null,
        ]);
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
        $lowestPrice = fn (Product $product) => $product->variants->min(fn ($variant) => $variant->price());

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
