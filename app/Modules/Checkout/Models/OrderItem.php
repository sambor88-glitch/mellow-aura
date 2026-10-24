<?php

namespace App\Modules\Checkout\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * custom_text is typed by the customer: limit its length on the server and
 * always print it escaped ({{ }} in Blade, never {!! !!}).
 */
#[Fillable([
    'order_id', 'product_variant_id', 'product_name', 'variant_label', 'quantity',
    'unit_price_gross', 'custom_text', 'custom_glaze',
])]
class OrderItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_gross' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function lineTotal(): int
    {
        return $this->unit_price_gross * $this->quantity;
    }
}
