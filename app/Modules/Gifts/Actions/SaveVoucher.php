<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Gifts\Models\Voucher;
use App\Modules\Workshops\Enums\BookingStatus;
use Illuminate\Support\Facades\DB;

/**
 * One voucher edited in the panel (MA-137): its workshop, seats and valid_until, and whether it is used. Until the
 * site takes codes, Kasia books by e-mail or WhatsApp (terms §8.5), so she marks such a voucher used by hand, or the
 * same code would book again on the site. A voucher used by a booking that still stands stays used: cancelling the
 * booking gives it back.
 */
class SaveVoucher
{
    /**
     * @param  array{workshop_code: ?string, seats: ?int, valid_until: string, redeemed: bool}  $data
     */
    public function __invoke(Voucher $voucher, array $data): Voucher
    {
        return DB::transaction(function () use ($voucher, $data) {
            $voucher = Voucher::query()->lockForUpdate()->findOrFail($voucher->id);
            $booked = $voucher->redeemed_at !== null && $voucher->latestBooking?->status === BookingStatus::Confirmed;

            $voucher->update([
                'workshop_code' => $data['workshop_code'],
                'seats' => $data['seats'],
                'valid_until' => $data['valid_until'],
                'redeemed_at' => match (true) {
                    $booked => $voucher->redeemed_at,
                    $data['redeemed'] => $voucher->redeemed_at ?? now(),
                    default => null,
                },
            ]);

            return $voucher;
        });
    }
}
