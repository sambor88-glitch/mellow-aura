<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Enums\RedemptionStatus;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;

/**
 * Puts voucher money back on the balance (MA-211). The voucher keeps its date: one that ran out meanwhile gets its
 * money back expired, and Kasia extends it in the panel if she wants to.
 *
 * __invoke() gives back everything an order took — Kasia does it from the order in the panel, instead of returning
 * that part of the money to the account. abandoned() gives back only what an unpaid order still holds: the payment
 * failed, the customer came back to place a new order, or the hold ran out (ReleaseVoucherHolds).
 */
class RestoreVoucherBalance
{
    /**
     * @return int what came back, in grosze
     */
    public function __invoke(Order $order): int
    {
        return $this->restore($order, [RedemptionStatus::Held, RedemptionStatus::Used]);
    }

    public function abandoned(Order $order): int
    {
        return $this->restore($order, [RedemptionStatus::Held], unpaidOnly: true);
    }

    /**
     * @param  list<RedemptionStatus>  $statuses
     */
    private function restore(Order $order, array $statuses, bool $unpaidOnly = false): int
    {
        return DB::transaction(function () use ($order, $statuses, $unpaidOnly) {
            $redemptions = VoucherRedemption::query()->where('order_id', $order->id)->whereIn('status', $statuses)
                ->orderBy('id')->lockForUpdate()->get();

            // Read after the lock: a payment confirmed meanwhile takes the money again in CaptureVoucherBalance.
            if ($redemptions->isEmpty() || ($unpaidOnly && Order::query()->whereKey($order->id)->value('payment_status') === PaymentStatus::Paid->value)) {
                return 0;
            }

            foreach ($redemptions as $redemption) {
                Voucher::query()->lockForUpdate()->findOrFail($redemption->voucher_id)->increment('balance_minor', $redemption->amount_minor);
                $redemption->update(['status' => RedemptionStatus::Returned, 'returned_at' => now()]);
            }

            return (int) $redemptions->sum('amount_minor');
        });
    }
}
