<?php

namespace App\Modules\Checkout\Events;

use App\Modules\Checkout\Models\Withdrawal;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer has just confirmed a withdrawal statement, which is already saved.
 */
class WithdrawalSubmitted
{
    use Dispatchable;

    public function __construct(public Withdrawal $withdrawal) {}
}
