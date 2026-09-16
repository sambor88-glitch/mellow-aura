<?php

namespace App\Modules\Gifts\Models;

use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Gifts\Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A voucher issued for a paid order item: one per piece, each with its own code.
 */
#[Fillable(['code', 'order_item_id', 'recipient_name', 'dedication', 'valid_until', 'redeemed_at'])]
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    /**
     * The order item it was bought with; its copies of the name and size say what the voucher is for.
     *
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'redeemed_at' => 'datetime',
        ];
    }
}
