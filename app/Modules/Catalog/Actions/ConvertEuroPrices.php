<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ExchangeRate;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Sets the euro prices of products marked euro_from_rate from their złoty prices at the latest NBP rate,
 * plus the markup from the panel (euro_markup_percent), rounded up to the next price ending in .90
 * (239 zł at 4.2653 plus 15% → €64.90). A price that did not move after rounding is left alone, so price_history
 * only learns real changes and Omnibus counts them per currency.
 */
class ConvertEuroPrices
{
    public const MARKUP = 'euro_markup_percent';

    public function __construct(private Settings $settings) {}

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

        $this->settings->refresh();
        $markup = (int) $this->settings->get(self::MARKUP, 0);

        return DB::transaction(fn () => $variants->sum(fn (ProductVariant $variant) => $this->convert($variant, $rate->tenThousandths(), $markup)));
    }

    /**
     * Grosze to euro cents with the markup in percent: the lowest price ending in .90 that is not below the exact
     * amount, never below €0.90. The rate is in ten-thousandths of a złoty, so grosze × (100 + markup) / rate is euros.
     */
    public static function euro(int $grosze, int $rate, int $markup = 0): int
    {
        // The smallest whole euro n with n − 0.10 ≥ the exact amount, in integers only.
        $euros = intdiv($grosze * (100 + $markup) * 10 + $rate + 10 * $rate - 1, 10 * $rate);

        return max(1, $euros) * 100 - 10;
    }

    private function convert(ProductVariant $variant, int $rate, int $markup): int
    {
        $amount = self::euro($variant->price_gross, $rate, $markup);
        $compareAt = $variant->compare_at_price === null ? null : self::euro($variant->compare_at_price, $rate, $markup);

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
