<?php

namespace App\Modules\Gifts\Models;

use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Enums\RedemptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One use of an amount voucher's balance on an order (MA-211), in grosze. The row stays after the money comes back,
 * so the panel shows the whole history of the voucher.
 *
 * @property int $amount_minor
 * @property RedemptionStatus $status
 * @property ?CarbonImmutable $used_at
 * @property ?CarbonImmutable $returned_at
 */
#[Fillable(['voucher_id', 'order_id', 'amount_minor', 'status', 'used_at', 'returned_at'])]
class VoucherRedemption extends Model
{
    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => RedemptionStatus::class,
            'used_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }
}
