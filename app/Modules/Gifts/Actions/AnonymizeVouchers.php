<?php

namespace App\Modules\Gifts\Actions;

use App\Modules\Gifts\Models\Voucher;

/**
 * Wipes the names and the dedication from the vouchers a customer bought, when she asks to be forgotten
 * (GDPR art. 17, MA-241). The code, the balance and the dates stay: whoever holds the voucher can still use it.
 */
class AnonymizeVouchers
{
    /**
     * @param  iterable<int>  $orderItemIds  the order items the vouchers were bought with
     * @return int how many vouchers were wiped
     */
    public function __invoke(iterable $orderItemIds): int
    {
        return Voucher::query()
            ->whereIn('order_item_id', collect($orderItemIds)->all())
            ->update(['recipient_name' => null, 'sender_name' => null, 'dedication' => null]);
    }
}
