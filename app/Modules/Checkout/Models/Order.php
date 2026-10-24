<?php

namespace App\Modules\Checkout\Models;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Enums\ShippingMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Amounts in grosze.
 *
 * @property int $total_gross
 * @property int $shipping_gross
 */
#[Fillable([
    'number', 'status', 'email', 'phone', 'name', 'shipping_method', 'shipping_address', 'locker_code',
    'shipping_gross', 'total_gross', 'payment_status', 'payment_provider_id', 'note', 'invoice_nip',
])]
class Order extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
        'payment_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'shipping_method' => ShippingMethod::class,
            'shipping_address' => 'array',
            'shipping_gross' => 'integer',
            'total_gross' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
