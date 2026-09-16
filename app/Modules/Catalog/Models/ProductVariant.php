<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use App\Modules\Shared\Support\LowestPrice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
     * The lowest and highest price on the shelf in grosze, or null when nothing is for sale.
     *
     * @return array{int, int}|null
     */
    public static function priceRange(): ?array
    {
        $prices = static::query()->inStock()->whereRelation('product', 'is_published', true)
            ->toBase()
            ->selectRaw('min(price_gross) as lowest, max(price_gross) as highest')
            ->first();

        return $prices?->lowest === null ? null : [(int) $prices->lowest, (int) $prices->highest];
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

    public function isInStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }

    /**
     * On a stamped product the variant without stock tracking is made to order with the customer's
     * own text ("Twój tekst"), next to the ready one-off mugs that each have a stock of 1.
     */
    public function takesCustomText(): bool
    {
        return $this->product->stamp_enabled && $this->stock === null;
    }

    /**
     * Variants in stock or without stock tracking.
     *
     * @param  Builder<ProductVariant>  $query
     */
    #[Scope]
    protected function inStock(Builder $query): void
    {
        $query->where(fn (Builder $stock) => $stock->whereNull('stock')->orWhere('stock', '>', 0));
    }

    /**
     * The lowest price in effect during the 30 days before the current price took effect
     * (Omnibus). Null when there is no discount or no earlier price to compare with,
     * in which case the crossed-out price must not be shown.
     */
    public function lowestPriceBeforeDiscount(): ?int
    {
        if ($this->compare_at_price === null || $this->compare_at_price <= $this->price_gross) {
            return null;
        }

        return LowestPrice::beforeCurrent($this->priceHistory()->orderBy('valid_from')->orderBy('id')->get());
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
