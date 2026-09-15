<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Takes sold pieces off the shelf once a payment is confirmed — never when the customer clicks
 * „Płacę”, or abandoned checkouts would empty the shelf. It runs inside the payment's transaction
 * and locks the variant row, so two payments for the last one-off can't both take it.
 */
class DecrementStock
{
    /**
     * Returns how many pieces were missing: 0 when all were on the shelf or the variant has no stock tracking.
     */
    public function __invoke(int $variantId, int $quantity): int
    {
        throw_if(DB::transactionLevel() === 0, LogicException::class, 'DecrementStock must run inside the payment transaction.');

        $variant = ProductVariant::query()->lockForUpdate()->find($variantId);

        if ($variant === null) {
            return $quantity;
        }

        if ($variant->stock === null) {
            return 0;
        }

        $taken = min($variant->stock, $quantity);
        $variant->update(['stock' => $variant->stock - $taken]);

        return $quantity - $taken;
    }
}
