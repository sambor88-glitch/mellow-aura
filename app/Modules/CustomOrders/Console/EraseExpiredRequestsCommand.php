<?php

namespace App\Modules\CustomOrders\Console;

use App\Modules\CustomOrders\Actions\EraseExpiredRequests;
use Illuminate\Console\Command;

/**
 * Runs EraseExpiredRequests once a night, after the backup (MA-312).
 */
class EraseExpiredRequestsCommand extends Command
{
    protected $signature = 'custom-orders:erase-expired';

    protected $description = 'Erase the customer data and photos of custom order requests kept longer than the privacy policy allows';

    public function handle(EraseExpiredRequests $erase): int
    {
        $result = $erase();

        $this->info('Requests erased: '.$result['requests'].', photos deleted: '.$result['photos'].', failed: '.$result['failed'].'.');

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
