<?php

namespace App\Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Size or version of a product with its own price and stock. Amounts in grosze.
 *
 * @property int $price_gross
 * @property int|null $compare_at_price
 * @property int $stock
 */
#[Fillable(['product_id', 'label', 'price_gross', 'compare_at_price', 'stock', 'sort_order'])]
class ProductVariant extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Every price, including the first one, lands in price_history.
        static::created(fn (self $variant) => $variant->recordPrice());

        static::updated(function (self $variant) {
            if ($variant->wasChanged('price_gross')) {
                $variant->recordPrice();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price_gross' => 'integer',
            'compare_at_price' => 'integer',
            'stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function isDiscounted(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price_gross;
    }

    /**
     * Lowest price in force at any moment of the $days before $moment, or null
     * when there is no history for that period.
     */
    public function lowestPriceBefore(CarbonInterface $moment, int $days = 30): ?int
    {
        $windowStart = $moment->copy()->subDays($days);

        $inForceAtStart = $this->priceHistory()
            ->where('valid_from', '<=', $windowStart)
            ->latest('valid_from')
            ->latest('id')
            ->value('price_gross');

        $changedInWindow = $this->priceHistory()
            ->where('valid_from', '>', $windowStart)
            ->where('valid_from', '<', $moment)
            ->min('price_gross');

        $prices = array_filter([$inForceAtStart, $changedInWindow], fn ($price) => $price !== null);

        return $prices === [] ? null : (int) min($prices);
    }

    private function recordPrice(): void
    {
        $this->priceHistory()->create([
            'price_gross' => $this->price_gross,
            'valid_from' => now(),
        ]);
    }
}
