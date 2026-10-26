<?php

namespace App\Modules\Checkout\Enums;

/**
 * What Kasia decides about a complaint. When she does not accept all of it the dispute is not resolved, and the
 * answer has to say whether she agrees to out-of-court proceedings (art. 32 of the consumer ADR act).
 */
enum ComplaintDecision: string
{
    case Accepted = 'accepted';
    case PartlyAccepted = 'partly_accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Uznaję',
            self::PartlyAccepted => 'Uznaję w części',
            self::Rejected => 'Nie uznaję',
        };
    }

    public function sentence(): string
    {
        return match ($this) {
            self::Accepted => 'Uznaję reklamację.',
            self::PartlyAccepted => 'Uznaję reklamację w części.',
            self::Rejected => 'Nie uznaję reklamacji.',
        };
    }

    public function leavesDispute(): bool
    {
        return $this !== self::Accepted;
    }
}
