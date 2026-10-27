<?php

namespace App\Modules\CustomOrders\Models;

use App\Modules\CustomOrders\Enums\PaymentLinkStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A link to pay a part of the quote or all of it, sent to the customer by mail (MA-144). Paying it places an order
 * in Checkout with one item and nothing to deliver; CustomOrderPayment ties the two.
 *
 * @property int $id
 * @property int $custom_order_request_id
 * @property string $token
 * @property int $amount
 * @property PaymentLinkStatus $status
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $paid_at
 */
#[Fillable(['custom_order_request_id', 'token', 'amount', 'status', 'expires_at', 'paid_at'])]
class PaymentLink extends Model
{
    /** How long the customer has to pay before Kasia has to send a new link. */
    public const VALID_DAYS = 14;

    protected $table = 'custom_order_payment_links';

    /**
     * @return BelongsTo<CustomOrderRequest, $this>
     */
    public function customOrderRequest(): BelongsTo
    {
        return $this->belongsTo(CustomOrderRequest::class);
    }

    /**
     * @return HasMany<CustomOrderPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomOrderPayment::class, 'custom_order_payment_link_id');
    }

    /**
     * Whether the customer can still start a payment with it: open, in date, for an idea Kasia has not declined.
     */
    public function isUsable(): bool
    {
        return $this->status === PaymentLinkStatus::Open
            && $this->expires_at->isFuture()
            && $this->customOrderRequest->status->takesPayments();
    }

    public function url(): string
    {
        return route('custom-orders.payment.show', $this->token);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentLinkStatus::class,
            'expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }
}
