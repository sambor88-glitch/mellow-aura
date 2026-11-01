<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Settings;
use Illuminate\Database\Eloquent\Builder;

/**
 * „Ostatnie sztuki” (MA-343): sizes in the shop that are sold out or down to the threshold set in the panel, so Kasia
 * knows what to make before it disappears. Only visible sizes of published products with counted pieces. A one-off
 * piece is one by nature: its single piece, or its sale, is no alarm.
 */
class LastPieces
{
    /** The settings key: how many pieces still count as the last ones. */
    public const THRESHOLD = 'last_pieces_threshold';

    public const DEFAULT_THRESHOLD = 1;

    public const MAX_THRESHOLD = 20;

    public function __construct(private readonly Settings $settings) {}

    public function threshold(): int
    {
        return max(1, min(self::MAX_THRESHOLD, (int) $this->settings->get(self::THRESHOLD, self::DEFAULT_THRESHOLD)));
    }

    /** Whether a size of this product is sold out or down to its last pieces. */
    public function isLow(Product $product, ProductVariant $variant): bool
    {
        return $this->watched($product, $variant) && $variant->stock <= $this->threshold();
    }

    public function isSoldOut(Product $product, ProductVariant $variant): bool
    {
        return $this->watched($product, $variant) && $variant->stock === 0;
    }

    /** Down to its last pieces, still on sale. */
    public function isLast(Product $product, ProductVariant $variant): bool
    {
        return $this->watched($product, $variant) && $variant->stock > 0 && $variant->stock <= $this->threshold();
    }

    /**
     * The numbers for the tile on „Na dziś”: sizes sold out, sizes down to their last pieces, and the people waiting
     * for any of them to come back (MA-266).
     *
     * @return array{soldOut: int, last: int, waiting: int, threshold: int}
     */
    public function summary(): array
    {
        $variants = $this->query()->withCount('stockNotifications')->get(['id', 'stock']);

        return [
            'soldOut' => $variants->where('stock', 0)->count(),
            'last' => $variants->where('stock', '>', 0)->count(),
            'waiting' => (int) $variants->sum('stock_notifications_count'),
            'threshold' => $this->threshold(),
        ];
    }

    /** @return Builder<ProductVariant> */
    public function query(): Builder
    {
        return ProductVariant::query()
            ->visible()
            ->whereNotNull('stock')
            ->where('stock', '<=', $this->threshold())
            ->whereHas('product', fn (Builder $product) => $product->where('is_published', true)->where('is_one_off', false));
    }

    private function watched(Product $product, ProductVariant $variant): bool
    {
        return $product->is_published && ! $product->is_one_off && ! $variant->is_hidden && $variant->stock !== null;
    }
}
