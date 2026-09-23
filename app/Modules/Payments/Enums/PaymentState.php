<?php

namespace App\Modules\Payments\Enums;

/**
 * What the provider says about one payment. Deliberately narrower than Stripe's own statuses:
 * everything that is neither money in nor a final refusal is „still going”.
 */
enum PaymentState: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Pending = 'pending';
}
