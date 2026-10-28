<?php

namespace App\Modules\Gifts\Support;

use App\Modules\Gifts\Enums\BalanceRefusal;
use App\Modules\Gifts\Exceptions\BalanceNotAccepted;
use App\Modules\Gifts\Models\Voucher;
use Illuminate\Support\Collection;

/**
 * How amount vouchers pay for an order (MA-211). The codes pay in the order they were typed in, each as much as it
 * holds and the order still needs; one that is not needed any more pays nothing and keeps its balance.
 * Vouchers are sold in złoty and pay in złoty only — an order in euro never takes them.
 */
class VoucherBalance
{
    /** More codes on one order than anyone carries, so a script cannot try codes through one order. */
    public const MAX_CODES = 5;

    /**
     * The voucher behind a code, if it can pay for something now. Checked without a lock, for the checkout's screen;
     * Actions\HoldVoucherBalance checks again under the lock.
     *
     * @throws BalanceNotAccepted
     */
    public static function usable(string $code, ?Voucher $voucher): Voucher
    {
        $refusal = match (true) {
            $voucher === null => BalanceRefusal::Unknown,
            ! $voucher->isAmount() => BalanceRefusal::Workshop,
            $voucher->isExpired() => BalanceRefusal::Expired,
            $voucher->redeemed_at !== null || $voucher->balance_minor <= 0 => BalanceRefusal::Spent,
            default => null,
        };

        if ($refusal !== null) {
            throw new BalanceNotAccepted($code, $refusal, $refusal === BalanceRefusal::Unknown ? null : $voucher);
        }

        return $voucher;
    }

    /**
     * @throws BalanceNotAccepted
     */
    public static function find(string $code): Voucher
    {
        return self::usable($code, Voucher::query()->where('code', VoucherCode::normalize($code))->first());
    }

    /**
     * What each code pays towards $total, in the order given. A code the sum no longer needs pays 0.
     *
     * @param  Collection<int, Voucher>  $vouchers
     * @return Collection<int, array{voucher: Voucher, amount: int}>
     */
    public static function split(Collection $vouchers, int $total): Collection
    {
        $left = max(0, $total);

        return $vouchers->map(function (Voucher $voucher) use (&$left) {
            $amount = min($left, (int) $voucher->balance_minor);
            $left -= $amount;

            return ['voucher' => $voucher, 'amount' => $amount];
        });
    }

    /**
     * The codes the checkout keeps, for the screen: what each would pay towards $total now. A code that stopped
     * paying since it was typed in drops out quietly; the pay button shows the new sum.
     *
     * @param  list<string>  $codes
     * @return Collection<int, array{voucher: Voucher, amount: int}>
     */
    public static function preview(array $codes, int $total): Collection
    {
        $vouchers = collect($codes)->map(function (string $code) {
            try {
                return self::find($code);
            } catch (BalanceNotAccepted) {
                return null;
            }
        })->filter()->unique('id')->values();

        return self::split($vouchers, $total);
    }
}
