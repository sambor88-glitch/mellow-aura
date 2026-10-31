<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Pieces on the shelf changed straight from the product list (MA-307), with − and + and „Cofnij”.
 * Stock does not count for Omnibus, so it saves at once. A size that was sold out and comes back tells the people
 * waiting for it, the same as a save of the whole card (SendRestockNotices, MA-266). Null means the pieces are not counted.
 */
class SetStock
{
    public function __construct(private SendRestockNotices $sendRestockNotices) {}

    public function __invoke(ProductVariant $variant, ?int $stock): void
    {
        $back = DB::transaction(function () use ($variant, $stock) {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            $wasOffShelf = $variant->stock === 0;
            $variant->update(['stock' => $stock]);

            return $wasOffShelf && $variant->isInStock() && ! $variant->is_hidden;
        });

        if ($back) {
            ($this->sendRestockNotices)($variant);
        }
    }
}
