<?php

namespace App\Modules\CustomOrders\Console;

use App\Modules\CustomOrders\Actions\PrunePhotos;
use Illuminate\Console\Command;

/**
 * Runs PrunePhotos once a night, after the backup (MA-312).
 */
class PrunePhotosCommand extends Command
{
    protected $signature = 'custom-orders:prune-photos';

    protected $description = 'Delete the photos of custom order requests kept longer than the privacy policy allows';

    public function handle(PrunePhotos $prune): int
    {
        $result = $prune();

        $this->info('Photos deleted: '.$result['photos'].', requests: '.$result['requests'].', failed: '.$result['failed'].'.');

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
