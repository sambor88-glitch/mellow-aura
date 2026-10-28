<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Checkout\Models\Order;
use App\Modules\Gifts\Enums\BalanceRefusal;
use App\Modules\Gifts\Enums\RedemptionStatus;
use App\Modules\Gifts\Exceptions\BalanceNotAccepted;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Models\VoucherRedemption;
use App\Modules\Gifts\Support\VoucherBalance;
use App\Modules\Gifts\Support\VoucherCode;
use Illuminate\Support\Facades\DB;

/**
 * Takes what amount vouchers pay for a new order off their balance (MA-211). Checkout calls it inside the transaction
 * that saves the order, so a refused code saves nothing. The vouchers are locked in id order while the money is
 * counted: two orders with the same code at once are served one after the other, and the second one only gets what
 * the first one left. The money stays held until the order is paid (CaptureVoucherBalance) or given up
 * (RestoreVoucherBalance::abandoned).
 */
class HoldVoucherBalance
{
    /**
     * @param  list<string>  $codes  as typed, in the order typed
     * @return int what the vouchers pay, in grosze; the rest is paid through the gateway
     *
     * @throws BalanceNotAccepted
     */
    public function __invoke(Order $order, array $codes): int
    {
        $codes = collect($codes)->map(fn (string $code) => [$code, VoucherCode::normalize($code)])->unique(1)->values();

        if ($codes->isEmpty()) {
            return 0;
        }

        throw_if($order->currency !== 'PLN' || $codes->count() > VoucherBalance::MAX_CODES, new BalanceNotAccepted((string) $codes->first()[0], BalanceRefusal::Unknown));

        return DB::transaction(function () use ($order, $codes) {
            $locked = Voucher::query()->whereIn('code', $codes->pluck(1))->orderBy('id')->lockForUpdate()->get()->keyBy('code');
            $vouchers = $codes->map(fn (array $code) => VoucherBalance::usable($code[0], $locked->get($code[1])));
            $held = 0;

            foreach (VoucherBalance::split($vouchers, $order->total_gross)->where('amount', '>', 0) as $use) {
                $use['voucher']->decrement('balance_minor', $use['amount']);

                VoucherRedemption::create([
                    'voucher_id' => $use['voucher']->id,
                    'order_id' => $order->id,
                    'amount_minor' => $use['amount'],
                    'status' => RedemptionStatus::Held,
                ]);

                $held += $use['amount'];
            }

            return $held;
        });
    }
}
