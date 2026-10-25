<?php

namespace App\Modules\Shipping\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Shipping\Actions\CreateShipment;
use App\Modules\Shipping\Actions\RefreshShipment;
use App\Modules\Shipping\Exceptions\ShipmentRefused;
use App\Modules\Shipping\Http\Requests\Admin\CreateShipmentRequest;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Support\ShipXClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * „Nadaj w InPost” on an order in the panel (MA-132): the parcel goes to ShipX in one click, the label comes back
 * as a PDF to print. The order's own status still moves only when Kasia says the parcel is on its way.
 */
class ShipmentController extends Controller
{
    /** Only these travel with InPost; pickup at the studio and vouchers sent by e-mail have nothing to send. */
    public const METHODS = ['parcel_locker', 'courier'];

    /** CreateShipmentRequest lets only a sendable order through, once ShipX is connected. */
    public function store(CreateShipmentRequest $request, Order $order, CreateShipment $create): RedirectResponse
    {
        if (Shipment::latestFor($order) !== null) {
            return to_route('admin.orders.show', $order)->with('panel_status', 'Przesyłka jest już nadana — etykieta czeka niżej.');
        }

        try {
            $weight = $request->validated('weight');
            $create($order, $request->validated('size'), $request->validated('target_point'), $weight !== null ? (float) $weight : null);
        } catch (ShipmentRefused $refusal) {
            return to_route('admin.orders.show', $order)->withInput()->withErrors(['shipment' => $refusal->getMessage()]);
        }

        return to_route('admin.orders.show', $order)->with('panel_status', 'Przesyłka nadana w InPost. Pobierz etykietę i naklej ją na paczkę.');
    }

    public function label(Order $order, RefreshShipment $refresh, ShipXClient $client): Response|RedirectResponse
    {
        $shipment = Shipment::latestFor($order);
        abort_if($shipment === null || ! ShipXClient::configured(), 404);

        $shipment = $refresh($shipment);

        if ($shipment->isPreparing()) {
            return to_route('admin.orders.show', $order)->with('panel_status', 'InPost jeszcze przygotowuje etykietę. Spróbuj za minutę.');
        }

        try {
            $label = $client->label($shipment->shipx_id);
        } catch (ConnectionException) {
            $label = null;
        }

        if ($label === null || ! $label->successful()) {
            return to_route('admin.orders.show', $order)->withErrors(['shipment' => 'Etykieta się nie pobrała. Spróbuj za chwilę albo pobierz ją w InPost Managerze.']);
        }

        return response($label->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="etykieta-'.$order->number.'.pdf"',
        ]);
    }

    public static function sendable(Order $order): bool
    {
        return $order->payment_status === PaymentStatus::Paid && in_array($order->shipping_method, self::METHODS, true);
    }
}
