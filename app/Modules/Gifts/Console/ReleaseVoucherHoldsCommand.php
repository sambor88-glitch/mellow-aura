<?php

namespace App\Modules\Gifts\Console;

use App\Modules\Gifts\Actions\ReleaseVoucherHolds;
use Illuminate\Console\Command;

class ReleaseVoucherHoldsCommand extends Command
{
    protected $signature = 'gifts:release-voucher-holds';

    protected $description = 'Gives voucher money back from orders that were never paid';

    public function handle(ReleaseVoucherHolds $release): int
    {
        $this->info('Orders released: '.$release());

        return self::SUCCESS;
    }
}
