<?php

namespace App\Modules\Payments\Przelewy24;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Przelewy24's REST API through Laravel's own HTTP client — no unofficial packages between the shop and
 * the money. It logs in with the shop id and the reports key.
 */
class Client
{
    /**
     * Registers a transaction and returns its token. The customer pays on Przelewy24's page under
     * redirectUrl($token), or with a BLIK code charged against the same token.
     *
     * @param  array<string, mixed>  $body  everything except merchantId, posId and sign, which are added here
     *
     * @throws Przelewy24Exception
     */
    public function register(array $body): string
    {
        $body = [
            'merchantId' => Account::merchantId(),
            'posId' => Account::posId(),
            ...$body,
            'sign' => Signature::make([
                'sessionId' => $body['sessionId'],
                'merchantId' => Account::merchantId(),
                'amount' => $body['amount'],
                'currency' => $body['currency'],
            ], Account::crc()),
        ];

        try {
            $response = $this->request()->post('/api/v1/transaction/register', $body);
        } catch (ConnectionException $exception) {
            throw new Przelewy24Exception('Brak połączenia z Przelewy24: '.$exception->getMessage(), previous: $exception);
        }

        $token = $response->json('data.token');

        if ($response->failed() || ! is_string($token) || $token === '') {
            throw new Przelewy24Exception('Przelewy24 odrzuciło rejestrację ('.$response->status().'): '.$response->body());
        }

        return $token;
    }

    /** Przelewy24's own page, where the customer picks a bank, a card or a wallet. */
    public function redirectUrl(string $token): string
    {
        return Account::baseUrl().'/trnRequest/'.$token;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(Account::baseUrl())
            ->withBasicAuth((string) Account::posId(), Account::apiKey())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(20);
    }
}
