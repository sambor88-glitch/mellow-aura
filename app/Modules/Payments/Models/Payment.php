<?php

namespace App\Modules\Payments\Models;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Models\Order;
use App\Modules\Payments\Enums\PaymentState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay for an order (see the migration). The order keeps its own payment status; this
 * row is what the provider was asked for and what it answered.
 */
class Payment extends Model
{
    protected $fillable = ['order_id', 'provider', 'session_id', 'amount', 'currency', 'method', 'status', 'token', 'provider_order_id'];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentState::class,
            'provider_order_id' => 'integer',
        ];
    }
}
