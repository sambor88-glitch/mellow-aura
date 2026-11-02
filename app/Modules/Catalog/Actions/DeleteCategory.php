<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CategoryTree;

/**
 * „Usuń półkę” in the panel (MA-351): only an empty one. Glina, Szycie and the vouchers stay; a shelf with products,
 * with products shown on it („Pokaż też w”) or with shelves under it says what to move first.
 */
class DeleteCategory
{
    /**
     * Why the shelf cannot go, in Kasia's words, or null when it can. The panel's list hands in its tree and counts,
     * so asking about every shelf costs no queries.
     */
    public static function blocker(Category $category, ?CategoryTree $tree = null): ?string
    {
        return match (true) {
            $category->isTop() => 'Kategorii głównej nie usuniesz — możesz zmienić jej nazwę.',
            $tree ? $tree->children($category)->isNotEmpty() : $category->children()->exists() => 'Najpierw przenieś albo usuń półki, które są w „'.$category->name.'”.',
            ($category->products_count ?? $category->products()->count()) > 0 => 'Najpierw przenieś produkty z tej półki na inną — w karcie produktu, „Rodzaj w sklepie”.',
            ($category->also_shown_products_count ?? $category->alsoShownProducts()->count()) > 0 => 'Najpierw odznacz tę półkę w „Pokaż też w” przy produktach, które się na niej pokazują.',
            default => null,
        };
    }

    /** @return bool false when the shelf is not empty and stays */
    public function __invoke(Category $category): bool
    {
        if (self::blocker($category) !== null) {
            return false;
        }

        $category->delete();

        return true;
    }
}
