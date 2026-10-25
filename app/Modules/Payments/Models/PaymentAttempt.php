<?php

namespace App\Modules\Payments\Models;

use App\Modules\Checkout\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One try at paying an order (see the migration). Status: pending, paid or failed.
 */
class PaymentAttempt extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    protected $fillable = ['order_id', 'provider', 'session_id', 'amount', 'currency', 'token', 'provider_order_id', 'status'];

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
            'provider_order_id' => 'integer',
        ];
    }
}
