<?php

namespace App\Modules\Payments\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Przelewy24's REST API v1, without an unofficial package.
 *
 * Checked against the official OpenAPI file https://developers.przelewy24.pl/yaml/en_documentation_1.0.yaml
 * (25.10.2026): hosts, Basic auth with posId and the API (reports) key, transaction/register, transaction/verify,
 * transaction/by/sessionId, paymentMethod/blik/chargeByCode, trnRequest/{token}, and the sign fields below.
 * Nothing here has run against the sandbox yet — Kasia's account comes after her business is registered (MA-26, MA-98).
 */
class Przelewy24Client
{
    private const LIVE = 'https://secure.przelewy24.pl';

    private const SANDBOX = 'https://sandbox.przelewy24.pl';

    /**
     * True when every key is in place; without them the shop pays with the stand-in (TestGateway).
     */
    public static function configured(): bool
    {
        return collect(['merchant_id', 'crc', 'api_key'])->every(fn (string $key) => filled(config('services.przelewy24.'.$key)));
    }

    public function merchantId(): int
    {
        return (int) config('services.przelewy24.merchant_id');
    }

    /** The shop's point of sale; Przelewy24 gives it the merchant id unless Kasia sets up another one. */
    public function posId(): int
    {
        return (int) (config('services.przelewy24.pos_id') ?: config('services.przelewy24.merchant_id'));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function register(array $body): Response
    {
        return $this->http()->post('/api/v1/transaction/register', $body);
    }

    public function chargeBlik(string $token, string $blikCode): Response
    {
        return $this->http()->post('/api/v1/paymentMethod/blik/chargeByCode', ['token' => $token, 'blikCode' => $blikCode]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function verify(array $body): Response
    {
        return $this->http()->put('/api/v1/transaction/verify', $body);
    }

    public function transaction(string $sessionId): Response
    {
        return $this->http()->get('/api/v1/transaction/by/sessionId/'.rawurlencode($sessionId));
    }

    /** The bank and card page Przelewy24 shows the customer for a registered transaction. */
    public function paymentPage(string $token): string
    {
        return $this->host().'/trnRequest/'.$token;
    }

    /**
     * Przelewy24's checksum: SHA-384 of the JSON of the given fields, in the order the documentation lists them,
     * with the CRC key last and with unescaped Unicode and slashes, as the documentation asks.
     *
     * @param  array<string, string|int>  $fields
     */
    public function sign(array $fields): string
    {
        $fields['crc'] = (string) config('services.przelewy24.crc');

        return hash('sha384', json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function host(): string
    {
        return config('services.przelewy24.sandbox') ? self::SANDBOX : self::LIVE;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->host())
            ->withBasicAuth((string) $this->posId(), (string) config('services.przelewy24.api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
