<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Gifts\Enums\VoucherRefusal;
use App\Modules\Gifts\Exceptions\VoucherNotAccepted;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\VoucherCode;
use Illuminate\Support\Facades\DB;

/**
 * Uses a workshop voucher for a booking, once. Workshops calls both steps inside the booking's transaction:
 * lockFor() finds the voucher by its code, locks it and checks it, then the seats are counted and the booking saved,
 * and __invoke() marks the voucher used. Two people typing the same code at once are counted one after the other,
 * and the second one hears that it is used. A refused code throws, so nothing of the booking is saved.
 */
class RedeemVoucher
{
    /**
     * @param  string  $workshopCode  the workshop of the date being booked
     *
     * @throws VoucherNotAccepted
     */
    public function lockFor(string $code, string $workshopCode): Voucher
    {
        $voucher = Voucher::query()->lockForUpdate()->where('code', VoucherCode::normalize($code))->first();

        $refusal = match (true) {
            $voucher === null => VoucherRefusal::Unknown,
            $voucher->redeemed_at !== null => VoucherRefusal::Redeemed,
            $voucher->isExpired() => VoucherRefusal::Expired,
            $voucher->workshop_code === null => VoucherRefusal::NoWorkshop,
            $voucher->workshop_code !== $workshopCode => VoucherRefusal::OtherWorkshop,
            default => null,
        };

        if ($refusal !== null) {
            throw new VoucherNotAccepted($refusal, $refusal === VoucherRefusal::Unknown ? null : $voucher);
        }

        return $voucher;
    }

    /**
     * Marks a voucher found by lockFor() as used.
     */
    public function __invoke(Voucher $voucher): void
    {
        DB::transaction(fn () => $voucher->update(['redeemed_at' => now()]));
    }
}
