<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CategoryTree;

/**
 * „Gdzie stoi” on a shelf's card in the panel (MA-351): the shelf, with everything under it, goes to the end of
 * another one. The same move as a drag, for a keyboard and for a phone where dragging between branches is fiddly.
 */
class MoveCategory
{
    public function __construct(private ArrangeCategories $arrangeCategories) {}

    public function __invoke(Category $category, Category $parent): void
    {
        if ($category->parent_id === $parent->id) {
            return;
        }

        $rows = collect(ArrangeCategories::current(CategoryTree::load()));
        $row = ['id' => $category->id, 'parent_id' => $parent->id];

        ($this->arrangeCategories)($rows->reject(fn (array $item) => $item['id'] === $category->id)->push($row)->values()->all());
    }
}
