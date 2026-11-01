<?php

namespace App\Modules\Shipping\Actions;

use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Settings\Settings;
use App\Modules\Shipping\Exceptions\ShipmentRefused;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Support\ShipXClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Sends an order's parcel to ShipX (MA-132): to the customer's parcel locker or by courier to her address.
 * Kasia drops it at her own locker from the settings (parcel_locker_code); without one InPost's default for her
 * account applies. The sender is Kasia's InPost account, so the studio's address never leaves the shop.
 */
class CreateShipment
{
    /** Courier parcels in millimetres, the same three heights as the locker's A, B and C. */
    private const DIMENSIONS = [
        'small' => ['length' => 640, 'width' => 380, 'height' => 80],
        'medium' => ['length' => 640, 'width' => 380, 'height' => 190],
        'large' => ['length' => 640, 'width' => 380, 'height' => 410],
    ];

    public function __construct(private ShipXClient $client, private Settings $settings, private Alerts $alerts) {}

    /**
     * @throws ShipmentRefused
     */
    public function __invoke(Order $order, string $size, ?string $targetPoint = null, ?float $weightKg = null): Shipment
    {
        $locker = $order->shipping_method === 'parcel_locker';
        $service = $locker ? Shipment::LOCKER : Shipment::COURIER;

        $body = [
            'receiver' => $this->receiver($order, $locker),
            'parcels' => $locker
                ? ['template' => $size]
                : [['dimensions' => [...self::DIMENSIONS[$size], 'unit' => 'mm'], 'weight' => ['amount' => $weightKg, 'unit' => 'kg']]],
            'service' => $service,
            'reference' => $order->number,
            'custom_attributes' => array_filter([
                'target_point' => $locker ? $targetPoint : null,
                ...($locker && filled($dropoff = $this->settings->get('parcel_locker_code')) ? ['sending_method' => 'parcel_locker', 'dropoff_point' => $dropoff] : []),
            ]),
        ];

        try {
            $response = $this->client->createShipment($body);
        } catch (ConnectionException $exception) {
            $this->alerts->send('shipx-create', 'InPost ShipX nie odpowiada', $order->number.': '.$exception->getMessage());

            throw new ShipmentRefused('InPost nie odpowiada. Spróbuj za kilka minut — zamówienie czeka bez zmian.');
        }

        if (! $response->successful() || ! is_numeric($response->json('id'))) {
            throw new ShipmentRefused($this->refusal($response, $targetPoint));
        }

        return Shipment::create([
            'order_id' => $order->id,
            'shipx_id' => (int) $response->json('id'),
            'service' => $service,
            'size' => $size,
            'target_point' => $locker ? $targetPoint : null,
            'tracking_number' => $response->json('tracking_number'),
            'status' => (string) $response->json('status', 'created'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function receiver(Order $order, bool $locker): array
    {
        $name = Str::of((string) $order->name)->squish();
        $receiver = [
            'first_name' => (string) $name->before(' '),
            'last_name' => (string) ($name->contains(' ') ? $name->after(' ') : $name),
            'email' => $order->email,
            // ShipX takes nine digits, without +48.
            'phone' => substr((string) preg_replace('/\D+/', '', (string) $order->phone), -9),
        ];

        if ($locker) {
            return $receiver;
        }

        $address = (array) $order->shipping_address;
        // „Długa 12/3” → street „Długa”, building „12/3”: ShipX wants the number on its own.
        preg_match('/^(.*?)\s+(\d[\w\/-]*)$/u', trim((string) ($address['street'] ?? '')), $street);

        return [...$receiver, 'address' => [
            'street' => $street[1] ?? (string) ($address['street'] ?? ''),
            'building_number' => $street[2] ?? '',
            'city' => (string) ($address['city'] ?? ''),
            'post_code' => (string) ($address['postal_code'] ?? ''),
            'country_code' => 'PL',
        ]];
    }

    /** What went wrong, in a sentence Kasia can act on; ShipX's own words only when nothing better fits. */
    private function refusal(Response $response, ?string $targetPoint): string
    {
        $fields = array_keys(Arr::dot((array) $response->json('details', [])));
        $mentions = fn (string $field) => collect($fields)->contains(fn (string $path) => str_contains($path, $field));

        return match (true) {
            $response->status() === 401 || $response->status() === 403 => 'InPost nie przyjął klucza ShipX. Trzeba sprawdzić INPOST_SHIPX_TOKEN na serwerze.',
            $mentions('target_point') => 'InPost nie zna paczkomatu '.$targetPoint.'. Sprawdź kod z klientem albo wybierz najbliższy na inpost.pl/znajdz-paczkomat.',
            $mentions('phone') => 'InPost nie przyjął numeru telefonu klienta. Sprawdź go w zamówieniu.',
            $mentions('address') || $mentions('post_code') => 'InPost nie przyjął adresu klienta. Sprawdź ulicę, numer i kod pocztowy.',
            default => 'InPost nie przyjął przesyłki'.(filled($message = $response->json('message')) ? ': '.$message : '.').' Spróbuj jeszcze raz albo nadaj ją w InPost Managerze.',
        };
    }
}
