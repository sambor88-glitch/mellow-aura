<?php

namespace App\Modules\Gifts\Models;

use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Gifts\Database\Factories\VoucherFactory;
use App\Modules\Workshops\Models\WorkshopBooking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A voucher issued for a paid order item: one per piece, each with its own code. A workshop voucher knows its workshop
 * and seats and books them on the site with its code, once (Actions\RedeemVoucher).
 *
 * @property ?string $workshop_code a row of the workshop price list; null for an amount voucher
 * @property ?int $seats
 * @property CarbonImmutable $valid_until
 * @property ?CarbonImmutable $redeemed_at
 */
#[Fillable(['code', 'order_item_id', 'recipient_name', 'sender_name', 'dedication', 'workshop_code', 'seats', 'valid_until', 'redeemed_at'])]
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
     * The booking it paid for last; a cancelled one gave the voucher back.
     *
     * @return HasOne<WorkshopBooking, $this>
     */
    public function latestBooking(): HasOne
    {
        return $this->hasOne(WorkshopBooking::class)->latestOfMany();
    }

    /** Valid through the whole day printed on it. */
    public function isExpired(): bool
    {
        return $this->valid_until->endOfDay()->isPast();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'valid_until' => 'date',
            'redeemed_at' => 'datetime',
        ];
    }
}
