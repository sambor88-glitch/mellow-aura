<?php

namespace App\Modules\Shipping\Actions;

use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Support\ShipXClient;
use Illuminate\Http\Client\ConnectionException;

/**
 * Reads the parcel back from ShipX: the tracking number comes once InPost has bought the label, a moment after the
 * shipment was created. When ShipX does not answer, the shipment stays as it was.
 */
class RefreshShipment
{
    public function __construct(private ShipXClient $client) {}

    public function __invoke(Shipment $shipment): Shipment
    {
        try {
            $response = $this->client->shipment($shipment->shipx_id);
        } catch (ConnectionException) {
            return $shipment;
        }

        if ($response->successful()) {
            $shipment->update([
                'status' => (string) $response->json('status', $shipment->status),
                'tracking_number' => $response->json('tracking_number') ?: $shipment->tracking_number,
            ]);
        }

        return $shipment;
    }
}
