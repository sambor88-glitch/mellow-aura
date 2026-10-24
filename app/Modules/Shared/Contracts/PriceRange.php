<?php

namespace App\Modules\Shared\Contracts;

/**
 * Lowest and highest price on offer, for priceRange in the Organization markup.
 * Bound by the Catalog module; Shared does not read shop tables itself.
 */
interface PriceRange
{
    /**
     * @return array{0: int, 1: int}|null [min, max] in grosze, null when nothing is on sale
     */
    public function bounds(): ?array;
}
