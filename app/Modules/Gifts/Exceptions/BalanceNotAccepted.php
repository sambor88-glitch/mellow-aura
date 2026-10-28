<?php

namespace App\Modules\Gifts\Exceptions;

use App\Modules\Gifts\Enums\BalanceRefusal;
use App\Modules\Gifts\Models\Voucher;
use RuntimeException;

/**
 * A code that pays nothing at the checkout. Thrown inside the order's transaction, so nothing of the order is saved.
 */
class BalanceNotAccepted extends RuntimeException
{
    public function __construct(public readonly string $typedCode, public readonly BalanceRefusal $reason, public readonly ?Voucher $voucher = null)
    {
        parent::__construct('Voucher balance not accepted: '.$reason->name);
    }
}
