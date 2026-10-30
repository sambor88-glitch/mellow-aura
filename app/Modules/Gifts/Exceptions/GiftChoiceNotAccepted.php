<?php

namespace App\Modules\Gifts\Exceptions;

use App\Modules\Gifts\Enums\GiftChoiceRefusal;
use RuntimeException;

/**
 * A card that chooses nothing now. Thrown inside the redemption's transaction, so no order is saved.
 */
class GiftChoiceNotAccepted extends RuntimeException
{
    public function __construct(public readonly GiftChoiceRefusal $refusal)
    {
        parent::__construct('Gift choice not accepted: '.$refusal->name);
    }
}
