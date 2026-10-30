<?php

namespace App\Modules\Gifts\Enums;

/**
 * Why a gift choice card cannot be used now (MA-235).
 */
enum GiftChoiceRefusal: string
{
    case Unknown = 'unknown';
    case Chosen = 'chosen';
    case Expired = 'expired';

    public function message(): string
    {
        return __('gifts::choice.refusal.'.$this->value);
    }
}
