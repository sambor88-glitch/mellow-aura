<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Enums\RedemptionStatus;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;

/**
 * Spends the voucher money held for an order once the order is paid (MA-211). Checkout\Actions\MarkOrderPaid calls it
 * inside its own transaction, so the balance and the payment are settled together; an order paid by vouchers alone
 * goes through here too. Money given back meanwhile (the payment came after RestoreVoucherBalance::abandoned) is taken
 * again if the voucher still has it. If it does not, the order lacks that money: Kasia hears about it on the order.
 */
class CaptureVoucherBalance
{
    /**
     * @return ?string what Kasia reads on the order under „Problem” when a voucher no longer covers its part
     */
    public function __invoke(Order $order): ?string
    {
        return DB::transaction(function () use ($order) {
            $redemptions = VoucherRedemption::query()->where('order_id', $order->id)->whereIn('status', [RedemptionStatus::Held, RedemptionStatus::Returned])
                ->orderBy('id')->lockForUpdate()->get();
            $short = [];

            foreach ($redemptions as $redemption) {
                if ($redemption->status === RedemptionStatus::Returned) {
                    $voucher = Voucher::query()->lockForUpdate()->findOrFail($redemption->voucher_id);

                    if ($voucher->balance_minor < $redemption->amount_minor) {
                        $short[] = $voucher->code.' ('.$order->money($redemption->amount_minor).')';

                        continue;
                    }

                    $voucher->decrement('balance_minor', $redemption->amount_minor);
                }

                $redemption->update(['status' => RedemptionStatus::Used, 'used_at' => now(), 'returned_at' => null]);
            }

            return $short === [] ? null : 'Płatność przyszła po czasie, a voucher '.implode(', ', $short)
                .' został w międzyczasie wydany — do zamówienia brakuje tej kwoty. Poproś o dopłatę albo zwróć wpłatę.';
        });
    }
}
