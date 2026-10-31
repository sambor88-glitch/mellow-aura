<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Puts the whole list in the order Kasia dragged it into (MA-307), which is also the order in the shop and on the home page.
 * Positions are renumbered from 1. A list that no longer matches the products — one added or deleted on another
 * device in the meantime — changes nothing, so no product falls out of the order.
 */
class ReorderProducts
{
    /**
     * @param  list<int>  $ids  every product, in the new order
     */
    public function __invoke(array $ids): bool
    {
        return DB::transaction(function () use ($ids) {
            $current = Product::query()->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $given = collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($current !== $given) {
                return false;
            }

            foreach (array_values($ids) as $position => $id) {
                Product::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }

            return true;
        });
    }
}
