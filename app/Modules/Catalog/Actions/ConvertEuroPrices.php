<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ExchangeRate;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Sets the euro prices of products marked euro_from_rate from their złoty prices at the latest NBP rate,
 * rounded to the nearest whole euro and ending in .90 (239 zł at 4.2653 → €55.90). A price that did not move
 * after rounding is left alone, so price_history only learns real changes and Omnibus counts them per currency.
 */
class ConvertEuroPrices
{
    /**
     * @return int how many euro prices were created or changed
     */
    public function __invoke(?Product $product = null): int
    {
        $rate = ExchangeRate::for('EUR');

        if ($rate === null) {
            return 0;
        }

        $variants = ProductVariant::query()
            ->whereHas('product', fn ($products) => $products->where('euro_from_rate', true))
            ->when($product, fn ($variants) => $variants->where('product_id', $product->id))
            ->get();

        return DB::transaction(fn () => $variants->sum(fn (ProductVariant $variant) => $this->convert($variant, $rate->tenThousandths())));
    }

    /**
     * Grosze to euro cents: the nearest whole euro, minus ten cents, never below €0.90.
     */
    public static function euro(int $grosze, int $rate): int
    {
        $euros = intdiv(2 * $grosze * 100 + $rate, 2 * $rate);

        return max(1, $euros) * 100 - 10;
    }

    private function convert(ProductVariant $variant, int $rate): int
    {
        $amount = self::euro($variant->price_gross, $rate);
        $compareAt = $variant->compare_at_price === null ? null : self::euro($variant->compare_at_price, $rate);

        $price = $variant->prices()->firstOrNew(['currency' => 'EUR']);
        // Rounding can bring the two together; a „before” price no higher than the price is no reduction.
        $price->fill(['amount_minor' => $amount, 'compare_at_minor' => $compareAt > $amount ? $compareAt : null]);

        if (! $price->isDirty()) {
            return 0;
        }

        $price->save();

        return 1;
    }
}
