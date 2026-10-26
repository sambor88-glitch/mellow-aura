<?php

namespace App\Modules\Checkout\Models;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Shared\Support\Money;
use App\Modules\Workshops\Models\WorkshopBooking;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'number', 'status', 'name', 'email', 'phone', 'shipping_method', 'shipping_address', 'delivery_country', 'locker_code', 'tracking_number',
    'shipping_gross', 'total_gross', 'currency', 'eur_rate', 'eur_rate_on', 'locale', 'payment_method', 'payment_status', 'payment_provider_id', 'paid_at', 'shipped_at', 'completed_at',
    'note', 'problem_note', 'invoice_nip', 'terms_version', 'terms_accepted_at',
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
     * @return HasMany<Withdrawal, $this>
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * The seats this order books on a workshop date, when it was placed from the booking form (Workshops).
     * Such an order has its own confirmation page and never touches the cart.
     *
     * @return HasOne<WorkshopBooking, $this>
     */
    public function workshopBooking(): HasOne
    {
        return $this->hasOne(WorkshopBooking::class);
    }

    /**
     * An amount from this order — its total, delivery, an item — in the currency it was paid in.
     */
    public function money(int $amount): string
    {
        return Money::format($amount, $this->currency ?? 'PLN');
    }

    /**
     * Whether something goes out in a parcel. An order of vouchers sent as PDFs has nothing to wrap or wait for.
     */
    public function sendsParcel(): bool
    {
        return $this->shipping_method !== ShippingMethods::EMAIL;
    }

    /**
     * Where the customer follows her parcel. Every parcel goes with InPost, to a locker or by courier.
     */
    public function trackingUrl(): ?string
    {
        return $this->tracking_number ? 'https://inpost.pl/sledzenie-przesylek?number='.rawurlencode($this->tracking_number) : null;
    }

    /**
     * Some pieces were already gone from the shelf when the payment came in.
     */
    public function hasShortage(): bool
    {
        return $this->items()->where('missing_quantity', '>', 0)->exists();
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
            'eur_rate' => 'decimal:4',
            'eur_rate_on' => 'date',
            'shipping_gross' => 'integer',
            'total_gross' => 'integer',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }
}
