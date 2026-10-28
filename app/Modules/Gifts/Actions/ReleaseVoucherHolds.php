<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Enums\RedemptionStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gives voucher money back from orders nobody paid for (MA-211), so a closed tab never locks a gift card for good.
 * An online payment has HOLD_MINUTES; a bank transfer takes days, so it has HOLD_TRANSFER_DAYS. A payment that comes
 * later still counts: CaptureVoucherBalance takes the money again if the voucher has it.
 */
class ReleaseVoucherHolds
{
    public const HOLD_MINUTES = 60;

    public const HOLD_TRANSFER_DAYS = 7;

    public function __construct(private RestoreVoucherBalance $restore) {}

    /**
     * @return int how many orders gave their voucher money back
     */
    public function __invoke(): int
    {
        $orders = Order::query()
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->whereHas('voucherRedemptions', fn (Builder $redemption) => $redemption->where('status', RedemptionStatus::Held))
            ->where(fn (Builder $stale) => $stale
                ->where(fn (Builder $online) => $online->where('payment_method', '!=', PaymentMethod::BankTransfer)->where('created_at', '<', now()->subMinutes(self::HOLD_MINUTES)))
                ->orWhere(fn (Builder $transfer) => $transfer->where('payment_method', PaymentMethod::BankTransfer)->where('created_at', '<', now()->subDays(self::HOLD_TRANSFER_DAYS))))
            ->get();

        return $orders->filter(fn (Order $order) => $this->restore->abandoned($order) > 0)->count();
    }
}
