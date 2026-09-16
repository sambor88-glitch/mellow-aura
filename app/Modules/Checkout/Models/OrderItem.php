<?php

namespace App\Modules\Checkout\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_variant_id', 'product_name', 'variant_label', 'quantity', 'missing_quantity', 'is_made_to_order', 'unit_price_gross',
    'custom_text', 'custom_glaze', 'recipient_name', 'dedication',
])]
class OrderItem extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function total(): int
    {
        return $this->unit_price_gross * $this->quantity;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'missing_quantity' => 'integer',
            'is_made_to_order' => 'boolean',
            'unit_price_gross' => 'integer',
        ];
    }
}
