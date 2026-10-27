<?php

namespace App\Modules\CustomOrders\Models;

use App\Modules\Checkout\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One try to pay a link: the order in Checkout, the amount it pays and when the payment came.
 * The order holds the customer and the payment; this row says which idea the money is for.
 *
 * @property int $id
 * @property int $custom_order_payment_link_id
 * @property int $order_id
 * @property int $amount
 * @property ?CarbonImmutable $paid_at
 */
#[Fillable(['custom_order_payment_link_id', 'order_id', 'amount', 'paid_at'])]
class CustomOrderPayment extends Model
{
    /**
     * @return BelongsTo<PaymentLink, $this>
     */
    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class, 'custom_order_payment_link_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'immutable_datetime',
        ];
    }
}
