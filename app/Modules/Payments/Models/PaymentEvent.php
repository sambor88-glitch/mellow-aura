<?php

namespace App\Modules\Payments\Models;

use App\Modules\Checkout\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    protected $fillable = ['provider', 'event_id', 'type', 'payment_provider_id', 'order_id', 'payload', 'note', 'handled_at'];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Writes down what we did with the call, so a payment that went wrong can be read back later.
     */
    public function handled(string $note): void
    {
        $this->update(['note' => $note, 'handled_at' => now()]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'handled_at' => 'datetime',
        ];
    }
}
