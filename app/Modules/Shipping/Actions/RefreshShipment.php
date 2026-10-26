<?php

namespace App\Modules\Shipping\Actions;

use App\Modules\Checkout\Actions\ChangeOrderStatus;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Shipping\Exceptions\ShipXUnavailable;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Support\ShipXClient;
use App\Modules\Shipping\Support\ShipXStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;

/**
 * Reads the parcel back from ShipX: the tracking number comes once InPost has bought the label, a moment after the
 * shipment was created. A new status moves the order along (MA-133) — only through Checkout's own action, only
 * forward, and only when ShipX's status has changed, so Kasia's own choice in the panel is never undone.
 */
class RefreshShipment
{
    public function __construct(private ShipXClient $client, private ChangeOrderStatus $changeStatus) {}

    /**
     * @throws ShipXUnavailable when ShipX does not answer or answers with an error; the shipment stays as it was.
     */
    public function __invoke(Shipment $shipment): Shipment
    {
        try {
            $response = $this->client->shipment($shipment->shipx_id);
        } catch (ConnectionException $exception) {
            throw new ShipXUnavailable($exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            throw new ShipXUnavailable('ShipX answered '.$response->status().' for shipment '.$shipment->shipx_id);
        }

        $previous = $shipment->status;

        DB::transaction(function () use ($shipment, $response, $previous) {
            $shipment->update([
                'status' => (string) $response->json('status', $previous),
                'tracking_number' => $response->json('tracking_number') ?: $shipment->tracking_number,
            ]);

            if ($shipment->status !== $previous) {
                $this->moveOrder($shipment);
            }
        });

        return $shipment;
    }

    private function moveOrder(Shipment $shipment): void
    {
        $order = Order::query()->lockForUpdate()->find($shipment->order_id);

        if ($order === null) {
            return;
        }

        match (ShipXStatus::stage($shipment->status)) {
            // Once: an order already sent (by Kasia's click or an earlier sync) keeps its date and its one mail.
            ShipXStatus::ON_THE_WAY => in_array($order->status, [OrderStatus::InProgress, OrderStatus::Problem], true) && $order->shipped_at === null
                ? ($this->changeStatus)($order, OrderStatus::Shipped, $shipment->tracking_number)
                : null,
            ShipXStatus::DELIVERED => in_array($order->status, [OrderStatus::InProgress, OrderStatus::Shipped, OrderStatus::Problem], true)
                ? ($this->changeStatus)($order, OrderStatus::Completed)
                : null,
            // A problem Kasia already marked keeps her own note.
            ShipXStatus::PROBLEM => in_array($order->status, [OrderStatus::InProgress, OrderStatus::Shipped], true)
                ? ($this->changeStatus)($order, OrderStatus::Problem, problemNote: $this->problemNote($shipment))
                : null,
            default => null,
        };
    }

    private function problemNote(Shipment $shipment): string
    {
        return 'InPost: '.ShipXStatus::label($shipment->status).'.'
            .($shipment->tracking_number ? ' Przesyłka '.$shipment->tracking_number.'.' : '')
            .' Sprawdź ją w InPost Managerze i napisz do klientki.';
    }
}
