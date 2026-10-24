<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

class InsufficientStock extends RuntimeException
{
    public static function for(int $variantId, int $requested, int $available): self
    {
        return new self("Variant {$variantId}: requested {$requested}, {$available} in stock.");
    }
}
