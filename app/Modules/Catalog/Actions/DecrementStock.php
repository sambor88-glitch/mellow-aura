<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\InsufficientStock;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Takes items off the shelf. Called by Payments when a payment is confirmed,
 * inside the same transaction as the confirmation — never from a queued listener.
 */
class DecrementStock
{
    /**
     * @param  array<int, int>  $quantities  variant id => quantity
     *
     * @throws InsufficientStock when any variant has less than requested; nothing is changed then
     */
    public function handle(array $quantities): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('DecrementStock must run inside the payment confirmation transaction.');
        }

        // Lock in id order so two webhooks for overlapping orders cannot deadlock.
        ksort($quantities);

        $variants = ProductVariant::query()
            ->whereKey(array_keys($quantities))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($quantities as $variantId => $quantity) {
            $available = $variants->get($variantId)?->stock ?? 0;

            if ($quantity > $available) {
                throw InsufficientStock::for($variantId, $quantity, $available);
            }
        }

        foreach ($quantities as $variantId => $quantity) {
            $variants->get($variantId)->decrement('stock', $quantity);
        }
    }
}
