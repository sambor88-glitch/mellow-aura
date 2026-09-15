<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'label', 'price_gross', 'compare_at_price', 'stock'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /**
     * Every price a variant has had lands in price_history, so a crossed-out price
     * can show the lowest price from the 30 days before the discount.
     */
    protected static function booted(): void
    {
        static::created(fn (ProductVariant $variant) => $variant->recordPrice());

        static::updated(function (ProductVariant $variant) {
            if ($variant->wasChanged('price_gross')) {
                $variant->recordPrice();
            }
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<PriceHistory, $this>
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    private function recordPrice(): void
    {
        $this->priceHistory()->create([
            'price_gross' => $this->price_gross,
            'valid_from' => now(),
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_gross' => 'integer',
            'compare_at_price' => 'integer',
            'stock' => 'integer',
        ];
    }
}
