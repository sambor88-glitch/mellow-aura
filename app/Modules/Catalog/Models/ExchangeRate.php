<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The latest NBP average rate of one currency (see the exchange_rates migration).
 */
#[Fillable(['currency', 'rate', 'effective_on'])]
class ExchangeRate extends Model
{
    public static function for(string $currency): ?self
    {
        return static::query()->where('currency', $currency)->first();
    }

    /**
     * The rate in ten-thousandths of a złoty (4.2653 → 42653), so prices are counted in whole numbers.
     */
    public function tenThousandths(): int
    {
        [$whole, $fraction] = explode('.', $this->rate.'.');

        return (int) $whole * 10000 + (int) str_pad(substr($fraction, 0, 4), 4, '0');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'string',
            'effective_on' => 'date',
        ];
    }
}
