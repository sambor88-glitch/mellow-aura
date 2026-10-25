<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Payments\Gateways\Przelewy24Gateway;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Models\PaymentEvent;
use App\Modules\Payments\Support\Przelewy24Client;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * What Przelewy24 tells us about a paid transaction (urlStatus). This is the only word that counts: the browser
 * can be closed or lie, so the shelf, the vouchers and the confirmation mail wait for this call.
 *
 * Order of checks: the signature with our CRC key, then the session and the sum against the try we registered,
 * then transaction/verify — Przelewy24 settles the money only after it — and only then MarkOrderPaid, which takes
 * the pieces off the shelf in one transaction with lockForUpdate. Every call is written to payment_events,
 * the refused ones too, and the same transaction arriving twice is handled once.
 */
class Przelewy24NotificationController extends Controller
{
    public function __construct(private Przelewy24Client $client, private Alerts $alerts) {}

    public function __invoke(Request $request, MarkOrderPaid $markOrderPaid): Response
    {
        // The documentation says JSON; all() reads a form-encoded body as well, should one ever come.
        $payload = $request->all();
        $orderId = (int) ($payload['orderId'] ?? 0);
        $sessionId = (string) ($payload['sessionId'] ?? '');

        if (! $this->signed($payload)) {
            $this->rejected($payload, 'Niepoprawny podpis — powiadomienie odrzucone');
            $this->alerts->send('p24-sign', 'Powiadomienie Przelewy24 z niepoprawnym podpisem', 'Sesja '.$sessionId.', transakcja '.$orderId.'. Zamówienie bez zmian.');

            return response('', 400);
        }

        $record = $this->record($payload, $orderId);

        if ($record->handled_at !== null) {
            return response('OK');
        }

        $attempt = PaymentAttempt::query()->where('session_id', $sessionId)->where('provider', Przelewy24Gateway::PROVIDER)->first();

        if ($attempt === null) {
            $record->handled('Płatność bez próby płatności w sklepie');
            $this->alerts->send('p24-orphan', 'Płatność Przelewy24 bez zamówienia', 'Przelewy24 zgłosiło sesję '.$sessionId.', której sklep nie zna.');

            return response('', 400);
        }

        $record->update(['order_id' => $attempt->order_id]);

        // A sum other than the one we asked for is never quietly accepted — nor the same number in another currency.
        // Without verify Przelewy24 does not settle it, so the money stays with the customer.
        if ((int) $payload['amount'] !== $attempt->amount || (string) $payload['currency'] !== $attempt->currency) {
            $paid = $payload['amount'].' '.$payload['currency'];
            $due = $attempt->amount.' '.$attempt->currency;
            $record->handled('Kwota '.$paid.' inna niż w próbie płatności ('.$due.', w groszach albo centach)');
            $this->alerts->send('p24-amount', 'Zapłacono inną kwotę', $attempt->order->number.': Przelewy24 mówi o '.$paid.', sklep prosił o '.$due.'. Zamówienie zostaje nieopłacone.');

            return response('', 400);
        }

        if (! $this->verified($attempt, $orderId)) {
            // Not handled: Przelewy24 sends the notification again, and the next try verifies again.
            $record->update(['note' => 'Weryfikacja w Przelewy24 nieudana, czeka na ponowienie']);

            return response('', 502);
        }

        $attempt->update(['status' => PaymentAttempt::PAID, 'provider_order_id' => $orderId]);
        $markOrderPaid($attempt->order, (string) $orderId);
        $record->handled('Zamówienie '.$attempt->order->number.' opłacone');

        return response('OK');
    }

    /**
     * The notification's checksum: the fields in the documented order, our merchant and point of sale, the CRC key.
     *
     * @param  array<string, mixed>  $payload
     */
    private function signed(array $payload): bool
    {
        foreach (['sessionId', 'amount', 'originAmount', 'currency', 'orderId', 'methodId', 'statement', 'sign'] as $field) {
            if (! array_key_exists($field, $payload)) {
                return false;
            }
        }

        $expected = $this->client->sign([
            'merchantId' => $this->client->merchantId(),
            'posId' => $this->client->posId(),
            'sessionId' => (string) $payload['sessionId'],
            'amount' => (int) $payload['amount'],
            'originAmount' => (int) $payload['originAmount'],
            'currency' => (string) $payload['currency'],
            'orderId' => (int) $payload['orderId'],
            'methodId' => (int) $payload['methodId'],
            'statement' => (string) $payload['statement'],
        ]);

        return is_string($payload['sign']) && hash_equals($expected, $payload['sign']);
    }

    private function verified(PaymentAttempt $attempt, int $orderId): bool
    {
        $body = [
            'merchantId' => $this->client->merchantId(),
            'posId' => $this->client->posId(),
            'sessionId' => $attempt->session_id,
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'orderId' => $orderId,
            'sign' => $this->client->sign([
                'sessionId' => $attempt->session_id,
                'orderId' => $orderId,
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
            ]),
        ];

        try {
            $response = $this->client->verify($body);
        } catch (ConnectionException $exception) {
            $this->alerts->send('p24-verify', 'Przelewy24 nie odpowiada przy weryfikacji', $attempt->session_id.': '.$exception->getMessage());

            return false;
        }

        if ($response->successful() && $response->json('data.status') === 'success') {
            return true;
        }

        $this->alerts->send('p24-verify', 'Przelewy24 nie potwierdziło płatności', $attempt->session_id.': HTTP '.$response->status().' '.Str::limit($response->body(), 300));

        return false;
    }

    /**
     * One record per Przelewy24 transaction, so the same notification arriving twice counts once.
     *
     * @param  array<string, mixed>  $payload
     */
    private function record(array $payload, int $orderId): PaymentEvent
    {
        $attributes = ['provider' => Przelewy24Gateway::PROVIDER, 'type' => 'transaction.paid', 'payment_provider_id' => (string) $orderId, 'payload' => $payload];

        try {
            return PaymentEvent::firstOrCreate(['event_id' => 'p24-'.$orderId], $attributes);
        } catch (UniqueConstraintViolationException) {
            // The same notification came twice at once; the other call holds it.
            return PaymentEvent::query()->where('event_id', 'p24-'.$orderId)->firstOrFail();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rejected(array $payload, string $note): void
    {
        // Its own record, so a forged call can never take the place of the real notification of that transaction.
        PaymentEvent::create([
            'provider' => Przelewy24Gateway::PROVIDER,
            'event_id' => 'p24-rejected-'.Str::uuid(),
            'type' => 'transaction.rejected',
            'payment_provider_id' => isset($payload['orderId']) ? (string) $payload['orderId'] : null,
            'payload' => $payload,
            'note' => $note,
            'handled_at' => now(),
        ]);
    }
}
