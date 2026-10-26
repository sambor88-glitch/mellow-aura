<?php

namespace App\Modules\Gifts\Models;

use App\Modules\Gifts\Database\Factories\BundleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A gift set: sizes from the shop sold together for less. Its price is never stored — it is the sum
 * of the parts' current prices minus the discount, so a price change in the shop reaches the set at once.
 */
#[Fillable(['name', 'description', 'discount_percent', 'is_published', 'sort_order'])]
class Bundle extends Model
{
    /** @use HasFactory<BundleFactory> */
    use HasFactory;

    public const MIN_PARTS = 2;

    /**
     * The parts' sum minus the discount, rounded to whole złoty like the prototype, in integers only.
     */
    public static function discounted(int $fullPrice, int $discountPercent): int
    {
        return intdiv($fullPrice * (100 - $discountPercent) + 5000, 10000) * 100;
    }

    /**
     * @return HasMany<BundleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * What the parts cost bought one by one, in grosze.
     */
    public function fullPrice(): int
    {
        return (int) $this->items->sum(fn (BundleItem $item) => $item->variant->price_gross);
    }

    public function price(): int
    {
        return self::discounted($this->fullPrice(), $this->discount_percent);
    }

    /**
     * The set's price shared out over its parts in proportion to their prices, for the order items.
     * The last part takes the remainder, so the shares always add up to the set's price.
     *
     * @return list<int>
     */
    public function partPrices(): array
    {
        $full = $this->fullPrice();
        $price = self::discounted($full, $this->discount_percent);
        $left = $price;
        $shares = [];

        foreach ($this->items->values() as $index => $item) {
            $share = $index === $this->items->count() - 1 || $full === 0
                ? $left
                : intdiv($item->variant->price_gross * $price, $full);

            $shares[] = $share;
            $left -= $share;
        }

        return $shares;
    }

    /**
     * Published sets of at least two parts, each of them on sale now.
     *
     * @param  Builder<Bundle>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('is_published', true)
            ->has('items', '>=', self::MIN_PARTS)
            ->whereDoesntHave('items.variant', fn (Builder $variant) => $variant->where(
                fn (Builder $unavailable) => $unavailable->where('stock', 0)->orWhereRelation('product', 'is_published', false),
            ));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_percent' => 'integer',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
