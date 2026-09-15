<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Moves a product one place up or down the list, which is also the order in the shop
 * and on the home page. Positions are renumbered from 1 on the way.
 */
class MoveProduct
{
    public function __invoke(Product $product, int $direction): void
    {
        DB::transaction(function () use ($product, $direction) {
            $ids = Product::query()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $from = array_search($product->id, $ids, true);

            if ($from === false || ! isset($ids[$from + $direction])) {
                return;
            }

            [$ids[$from], $ids[$from + $direction]] = [$ids[$from + $direction], $ids[$from]];

            foreach ($ids as $position => $id) {
                Product::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
