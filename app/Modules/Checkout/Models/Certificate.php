<?php

namespace App\Modules\Checkout\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The certificate of uniqueness for one handmade piece of an order, with the number printed on the card.
 */
#[Fillable(['order_item_id', 'piece', 'number'])]
class Certificate extends Model
{
    /**
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
            'piece' => 'integer',
        ];
    }
}
