<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CategoryTree;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The tree after a drag in the panel (MA-351): every shelf under Glina and Szycie with its parent, in the new order.
 * The top of the tree stays as it is, and so do the vouchers outside it. A shelf moved to the other branch takes its
 * group (the care rule) along with everything under it.
 *
 * The whole tree comes at once, so a list that changed in another window in the meantime is refused instead of
 * mixed: the panel then asks to refresh.
 */
class ArrangeCategories
{
    /**
     * @param  list<array{id: int, parent_id: int}>  $rows  in the order of the panel, a shelf before its children
     * @return bool false when the list does not match the shelves in the database
     *
     * @throws ValidationException when a shelf would land deeper than three levels or under itself
     */
    public function __invoke(array $rows): bool
    {
        return DB::transaction(function () use ($rows) {
            $tree = new CategoryTree(Category::query()->lockForUpdate()->get());
            $tops = $tree->shopTop()->keyBy('id');
            $arranged = $tops->flatMap(fn (Category $top) => $tree->inOrder($top))->pluck('id')->sort()->values()->all();
            $sent = collect($rows)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($arranged !== $sent) {
                return false;
            }

            $parents = collect($rows)->mapWithKeys(fn (array $row) => [(int) $row['id'] => (int) $row['parent_id']])->all();

            // Walk up from each shelf: it has to reach Glina or Szycie within three levels, without meeting itself.
            foreach (array_keys($parents) as $id) {
                $depth = 1;
                $step = $id;

                while (isset($parents[$step])) {
                    $step = $parents[$step];
                    $depth++;

                    if ($step === $id || $depth > Category::MAX_DEPTH) {
                        throw ValidationException::withMessages(['tree' => 'Tak głęboko się nie da — najwyżej trzy poziomy, np. Glina › Biżuteria › Kolczyki.']);
                    }
                }

                if (! $tops->has($step)) {
                    throw ValidationException::withMessages(['tree' => 'Każda półka stoi w Glinie albo w Szyciu.']);
                }
            }

            $positions = [];

            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $top = $id;

                while (isset($parents[$top])) {
                    $top = $parents[$top];
                }

                $tree->get($id)->forceFill([
                    'parent_id' => $parents[$id],
                    'sort_order' => $positions[$parents[$id]] = ($positions[$parents[$id]] ?? 0) + 1,
                    'group' => $tops[$top]->group ?? CategoryGroup::Ceramics,
                ])->save();
            }

            return true;
        });
    }

    /**
     * The tree as it stands, in the shape the panel sends it.
     *
     * @return list<array{id: int, parent_id: int}>
     */
    public static function current(CategoryTree $tree): array
    {
        return $tree->shopTop()
            ->flatMap(fn (Category $top) => $tree->inOrder($top))
            ->map(fn (Category $category) => ['id' => $category->id, 'parent_id' => (int) $category->parent_id])
            ->values()
            ->all();
    }
}
