<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Localization\Support\Locales;
use Illuminate\Support\Collection;

/**
 * The whole category tree from one query (MA-351) — a few dozen rows — for everything that walks it: the shop's chips,
 * breadcrumbs, the panel, sitemap.xml and the Google feed. Each category gets its parent from here, so asking about
 * the path or the language never goes back to the database.
 */
final class CategoryTree
{
    /** @var Collection<int, Category> by id */
    private Collection $categories;

    /** @var array<int, list<int>> parent id (0 for the top) => its children's ids, in shop order */
    private array $childIds = [];

    /**
     * @param  Collection<int, Category>  $categories
     */
    public function __construct(Collection $categories)
    {
        $sorted = $categories->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
        $this->categories = $sorted->keyBy('id');

        foreach ($sorted as $category) {
            $this->childIds[(int) $category->parent_id][] = $category->id;
        }

        foreach ($this->categories as $category) {
            $category->setRelation('parent', $category->parent_id === null ? null : $this->categories->get($category->parent_id));
        }
    }

    /** With the translations whenever a page reads another language, or when the panel asks for them. */
    public static function load(bool $translations = false): self
    {
        $translations = $translations || Locales::current() !== Locales::default();

        return new self(Category::query()->when($translations, fn ($query) => $query->with('translations'))->get());
    }

    public function get(?int $id): ?Category
    {
        return $id === null ? null : $this->categories->get($id);
    }

    /**
     * The children of a category, or the top of the tree for null.
     *
     * @return Collection<int, Category>
     */
    public function children(Category|int|null $parent = null): Collection
    {
        $id = $parent instanceof Category ? $parent->id : (int) $parent;

        return collect($this->childIds[$id] ?? [])->map(fn (int $child) => $this->categories[$child]);
    }

    /**
     * Glina and Szycie: the top of the tree without the vouchers, which have their own page.
     *
     * @return Collection<int, Category>
     */
    public function shopTop(): Collection
    {
        return $this->children()->reject(fn (Category $category) => $category->group === CategoryGroup::Workshops)->values();
    }

    /**
     * Every category in the order of the tree: a shelf, then everything under it.
     *
     * @return Collection<int, Category>
     */
    public function inOrder(Category|int|null $from = null): Collection
    {
        return $this->children($from)->flatMap(fn (Category $category) => [$category, ...$this->inOrder($category)])->values();
    }

    /**
     * From the top of the tree down to the category itself.
     *
     * @return Collection<int, Category>
     */
    public function path(Category|int $category): Collection
    {
        $path = [];
        $current = $this->get($category instanceof Category ? $category->id : $category);

        // The limit guards against a loop in damaged data; the tree is three levels deep.
        while ($current !== null && count($path) < 10) {
            array_unshift($path, $current);
            $current = $this->get($current->parent_id);
        }

        return collect($path);
    }

    /** 1 for Glina, 2 for Biżuteria, 3 for Kolczyki. */
    public function depth(Category $category): int
    {
        return $this->path($category)->count();
    }

    /** How many levels the category takes with everything under it: 1 for a shelf with no children. */
    public function height(Category $category): int
    {
        return 1 + (int) $this->children($category)->map(fn (Category $child) => $this->height($child))->max();
    }

    /**
     * The category and everything under it.
     *
     * @return list<int>
     */
    public function subtreeIds(Category|int $category): array
    {
        $id = $category instanceof Category ? $category->id : $category;

        return [$id, ...$this->inOrder($id)->pluck('id')->all()];
    }

    /**
     * [name, address] for each shelf from the top down to this one, in the language of the page — the trail
     * a product page and its JSON-LD show. Empty for a shelf with no page in this language.
     *
     * @return list<array{string, string}>
     */
    public function trail(Category|int|null $category): array
    {
        $path = $category === null ? collect() : $this->path($category);

        if ($path->isEmpty() || ! $path->last()->availableIn(Locales::current())) {
            return [];
        }

        return $path->map(fn (Category $step) => [$step->name, route('shop.category', $step)])->values()->all();
    }

    /** „Glina › Biżuteria › Kolczyki”, for the panel. */
    public function label(Category $category, string $separator = ' › '): string
    {
        return $this->path($category)->map(fn (Category $step) => $step->getRawOriginal('name'))->join($separator);
    }

    /**
     * How many of the products stand on each shelf, counting everything on the shelves under it. A product shown on two
     * shelves of one branch counts once.
     *
     * @param  Collection<int, Product>  $products  with alsoInCategories loaded
     * @return array<int, int> category id => products
     */
    public function counts(Collection $products): array
    {
        $found = [];

        foreach ($products as $product) {
            foreach ($product->shelfIds() as $shelf) {
                foreach ($this->path($shelf) as $category) {
                    $found[$category->id][$product->id] = true;
                }
            }
        }

        return array_map(count(...), $found);
    }
}
