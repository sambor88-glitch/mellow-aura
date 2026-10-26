<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A variant's price in one currency other than złoty (see the prices migration). Every amount it has had lands
 * in price_history under its currency.
 */
#[Fillable(['currency', 'amount_minor', 'compare_at_minor'])]
class Price extends Model
{
    protected static function booted(): void
    {
        static::saved(function (Price $price) {
            if ($price->wasRecentlyCreated || $price->wasChanged('amount_minor')) {
                $price->variant->priceHistory()->create([
                    'currency' => $price->currency,
                    'price_gross' => $price->amount_minor,
                    'valid_from' => now(),
                ]);
            }
        });
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'compare_at_minor' => 'integer',
        ];
    }
}
