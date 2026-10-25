<?php

namespace App\Modules\Shipping\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * InPost's ShipX API v1, without an unofficial package: create a shipment, read it back, download its label.
 *
 * Nothing here has run against InPost yet — Kasia's InPost account and its token come with MA-27. The tests talk
 * to a faked API only.
 */
class ShipXClient
{
    private const LIVE = 'https://api-shipx-pl.easypack24.net';

    private const SANDBOX = 'https://sandbox-api-shipx-pl.easypack24.net';

    /** True when the token and the organization are in place; without them the panel sends nothing to InPost. */
    public static function configured(): bool
    {
        return filled(config('services.inpost.shipx_token')) && filled(config('services.inpost.shipx_organization_id'));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function createShipment(array $body): Response
    {
        return $this->http()->post('/v1/organizations/'.rawurlencode((string) config('services.inpost.shipx_organization_id')).'/shipments', $body);
    }

    public function shipment(int $id): Response
    {
        return $this->http()->get('/v1/shipments/'.$id);
    }

    /** The label as a PDF in A6, the size of a label printer and of a quarter of an A4 sheet. */
    public function label(int $id): Response
    {
        return $this->http()->accept('application/pdf')->get('/v1/shipments/'.$id.'/label', ['format' => 'pdf', 'type' => 'A6']);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.inpost.shipx_sandbox') ? self::SANDBOX : self::LIVE)
            ->withToken((string) config('services.inpost.shipx_token'))
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
