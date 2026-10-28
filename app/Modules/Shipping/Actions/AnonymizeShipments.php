<?php

namespace App\Modules\Shipping\Actions;

use App\Modules\Shipping\Models\Shipment;

/**
 * Wipes the Paczkomat a customer had her parcels sent to, when she asks to be forgotten (GDPR art. 17, MA-241).
 * The parcel itself stays: its ShipX number, service, size and status.
 */
class AnonymizeShipments
{
    /**
     * @param  iterable<int>  $orderIds
     */
    public function __invoke(iterable $orderIds): int
    {
        return Shipment::query()->whereIn('order_id', collect($orderIds)->all())->update(['target_point' => null]);
    }
}
