<?php

namespace App\Modules\Catalog\Support;

use Illuminate\Support\Facades\Http;

/**
 * The current average rate from NBP table A — the one Polish accounting uses. No key needed.
 */
class NbpRates
{
    private const URL = 'https://api.nbp.pl/api/exchangerates/rates/a/%s/?format=json';

    /**
     * @return array{rate: string, effective_on: string} the rate as NBP prints it, e.g. "4.2653", and its date
     */
    public function latest(string $currency): array
    {
        $rate = Http::timeout(10)->retry(2, 1000)->acceptJson()
            ->get(sprintf(self::URL, strtolower($currency)))
            ->throw()
            ->json('rates.0');

        if (! is_array($rate) || ! is_numeric($rate['mid'] ?? null) || ! is_string($rate['effectiveDate'] ?? null)) {
            throw new \UnexpectedValueException("NBP sent no {$currency} rate.");
        }

        return ['rate' => number_format((float) $rate['mid'], 4, '.', ''), 'effective_on' => $rate['effectiveDate']];
    }
}
