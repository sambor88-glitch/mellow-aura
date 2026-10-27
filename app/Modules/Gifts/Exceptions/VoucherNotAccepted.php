<?php

namespace App\Modules\Gifts\Exceptions;

use App\Modules\Gifts\Enums\VoucherRefusal;
use App\Modules\Gifts\Models\Voucher;
use RuntimeException;

/**
 * A code that books nothing. Thrown inside the booking's transaction, so nothing of the booking is saved.
 */
class VoucherNotAccepted extends RuntimeException
{
    public function __construct(public readonly VoucherRefusal $reason, public readonly ?Voucher $voucher = null)
    {
        parent::__construct('Voucher not accepted: '.$reason->name);
    }
}
