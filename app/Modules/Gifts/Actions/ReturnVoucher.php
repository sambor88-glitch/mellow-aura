<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Gifts\Models\Voucher;
use Illuminate\Support\Facades\DB;

/**
 * Gives a voucher back when the booking it paid for is cancelled (Workshops\Actions\CancelBooking): it can book again
 * until the same day as before. One that ran out meanwhile comes back expired, and the panel shows it for Kasia to
 * extend by hand.
 */
class ReturnVoucher
{
    public function __invoke(int $voucherId): ?Voucher
    {
        return DB::transaction(function () use ($voucherId) {
            $voucher = Voucher::query()->lockForUpdate()->find($voucherId);
            $voucher?->update(['redeemed_at' => null]);

            return $voucher;
        });
    }
}
