<?php

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Actions\ConvertEuroPrices;
use App\Modules\Catalog\Models\ExchangeRate;
use App\Modules\Catalog\Support\NbpRates;
use Illuminate\Console\Command;
use Throwable;

/**
 * Fetches today's NBP euro rate and moves the euro prices that follow it. The scheduler runs it every hour:
 * NBP publishes table A around noon on working days, and a missed hour just waits for the next one.
 */
class SyncEuroRateCommand extends Command
{
    protected $signature = 'catalog:euro-rate';

    protected $description = 'Fetch the NBP euro rate and recount the euro prices that follow it';

    public function handle(NbpRates $nbp, ConvertEuroPrices $convert): int
    {
        try {
            $latest = $nbp->latest('EUR');
        } catch (Throwable $exception) {
            // The prices stay at the last rate; nothing on the shop breaks.
            $this->error('NBP did not answer: '.$exception->getMessage());

            return self::FAILURE;
        }

        ExchangeRate::query()->updateOrCreate(['currency' => 'EUR'], $latest);
        $changed = $convert();

        $this->info("EUR {$latest['rate']} from {$latest['effective_on']}, euro prices changed: {$changed}.");

        return self::SUCCESS;
    }
}
