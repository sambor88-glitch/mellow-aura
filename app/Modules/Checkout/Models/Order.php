<?php

namespace App\Modules\Checkout\Models;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'status', 'name', 'email', 'phone', 'shipping_method', 'shipping_address', 'locker_code',
    'shipping_gross', 'total_gross', 'payment_method', 'payment_status', 'payment_provider_id', 'paid_at',
    'note', 'invoice_nip',
])]
class Order extends Model
{
    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_address' => 'array',
            'shipping_gross' => 'integer',
            'total_gross' => 'integer',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }
}
