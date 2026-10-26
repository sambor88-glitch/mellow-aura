<?php

namespace App\Modules\Shipping\Console;

use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Shipping\Actions\RefreshShipment;
use App\Modules\Shipping\Exceptions\ShipXUnavailable;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Support\ShipXClient;
use App\Modules\Shipping\Support\ShipXStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Asks ShipX about every parcel still on its way and lets RefreshShipment move the orders (MA-133). The scheduler
 * runs it every 15 minutes. A parcel older than three weeks has long arrived or come back: Kasia checks it herself.
 *
 * No ShipX webhook: its calls carry nothing that proves they come from InPost, and asking costs a few requests.
 */
class SyncShipmentsCommand extends Command
{
    /** After this many runs in a row without a single answer, someone hears about it. */
    private const FAILED_RUNS_BEFORE_ALERT = 3;

    private const FAILED_RUNS = 'shipping:sync:failed-runs';

    protected $signature = 'shipping:sync';

    protected $description = 'Read the parcels on their way from InPost ShipX and move their orders along';

    public function handle(RefreshShipment $refresh, Alerts $alerts): int
    {
        if (! ShipXClient::configured()) {
            $this->info('ShipX is not configured, nothing to sync.');

            return self::SUCCESS;
        }

        $shipments = Shipment::query()
            ->whereNotIn('status', ShipXStatus::FINAL)
            ->where('created_at', '>=', now()->subDays(21))
            ->whereHas('order', fn ($order) => $order->where('status', '!=', OrderStatus::Completed))
            ->get();

        $failed = [];

        foreach ($shipments as $shipment) {
            try {
                $refresh($shipment);
            } catch (ShipXUnavailable $exception) {
                $failed[] = $shipment->shipx_id.': '.$exception->getMessage();
            }
        }

        $this->info('Shipments checked: '.$shipments->count().', not answered: '.count($failed).'.');

        if ($failed === [] || count($failed) < $shipments->count()) {
            Cache::forget(self::FAILED_RUNS);

            return self::SUCCESS;
        }

        $runs = (int) Cache::increment(self::FAILED_RUNS);

        if ($runs >= self::FAILED_RUNS_BEFORE_ALERT) {
            $alerts->send(
                key: 'shipping-sync',
                subject: 'InPost ShipX nie odpowiada od '.($runs * 15).' min',
                details: 'shipping:sync nie dostał odpowiedzi o żadnej przesyłce '.$runs." razy z rzędu. Zamówienia stoją w miejscu, dopóki ShipX nie odpowie.\n\n"
                    .implode("\n", array_slice($failed, 0, 10))
                    ."\n\nSprawdź INPOST_SHIPX_TOKEN na serwerze i status.inpost.pl.",
            );
        }

        return self::FAILURE;
    }
}
