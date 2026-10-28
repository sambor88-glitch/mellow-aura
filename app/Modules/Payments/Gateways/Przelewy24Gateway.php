<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Checkout\Actions\AttachPaymentReference;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Events\OrderPlaced;
use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Models\PaymentAttempt;
use App\Modules\Payments\Support\Przelewy24Client;
use App\Modules\Payments\Support\StartedPayment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;

/**
 * Przelewy24 (MA-167). Every payment is first registered with Przelewy24, in the order's currency and in grosze
 * or cents. BLIK takes the code from our own field and charges it straight away (BLIK Level 0); the customer
 * confirms in the bank's app while the checkout waits on its BLIK page (MA-101). Card, online transfer and BLIK
 * without Level 0 go to Przelewy24's own page and come back to the confirmation.
 *
 * The money counts only after Przelewy24's notification has been verified (Przelewy24NotificationController).
 * A bank transfer has nothing to confirm online: the order waits for the money and the customer gets the account
 * number in the order summary.
 */
class Przelewy24Gateway implements PaymentGateway
{
    public const PROVIDER = 'przelewy24';

    /** Transaction status in transaction/by/sessionId: 2 is „payment made”, after our verify. */
    private const PAID = 2;

    /**
     * BLIK's number in Przelewy24's list of methods (paymentMethods), for BLIK on their page. To be checked
     * against the list in the sandbox once the account is there (MA-98).
     */
    private const BLIK_METHOD = 154;

    public function __construct(
        private Przelewy24Client $client,
        private Alerts $alerts,
        private AttachPaymentReference $attachReference,
    ) {}

    public function start(Order $order, ?string $blikCode): StartedPayment
    {
        if ($order->payment_method === PaymentMethod::BankTransfer) {
            OrderPlaced::dispatch($order);

            return StartedPayment::done();
        }

        $blikHere = $order->payment_method === PaymentMethod::Blik && $this->takesBlikCode();
        $attempt = $this->register($order, $blikHere);

        if ($attempt === null) {
            return StartedPayment::rejected(__('payments::gateway.down'));
        }

        if ($blikHere) {
            $payment = $this->charge($order, $attempt, (string) $blikCode);

            // A refused code leaves no order behind for the customer, so the summary goes out only for a taken one.
            if ($payment->error === null) {
                OrderPlaced::dispatch($order);
            }

            return $payment;
        }

        OrderPlaced::dispatch($order);

        return StartedPayment::redirect($this->client->paymentPage((string) $attempt->token));
    }

    /**
     * Przelewy24 takes a session id only once, so a new code is a new registered try on the same order.
     */
    public function chargeBlik(Order $order, string $blikCode): StartedPayment
    {
        $attempt = $this->register($order, true);

        return $attempt === null
            ? StartedPayment::rejected(__('payments::gateway.down'))
            : $this->charge($order, $attempt, $blikCode);
    }

    /**
     * The customer cancelled on Przelewy24's page or closed it. Przelewy24 tells nothing about it, so the order
     * waits as unpaid, and a new registered try on the same order sends her back to the page (MA-219).
     */
    public function resume(Order $order): StartedPayment
    {
        $attempt = $this->register($order, false);

        return $attempt === null
            ? StartedPayment::rejected(__('payments::gateway.down'))
            : StartedPayment::redirect($this->client->paymentPage((string) $attempt->token));
    }

    /**
     * BLIK Level 0 has to be switched on by Przelewy24 for the shop. Until it is, PRZELEWY24_BLIK_ON_SITE=false
     * sends BLIK to Przelewy24's page, with BLIK already chosen (MA-101).
     */
    public function takesBlikCode(): bool
    {
        return (bool) config('services.przelewy24.blik_on_site', true);
    }

    public function state(Order $order): PaymentState
    {
        $attempt = PaymentAttempt::query()->where('order_id', $order->id)->where('provider', self::PROVIDER)->latest('id')->first();

        if ($attempt === null || $attempt->status === PaymentAttempt::FAILED) {
            return $attempt === null ? PaymentState::Pending : PaymentState::Failed;
        }

        if ($attempt->status === PaymentAttempt::PAID) {
            return PaymentState::Succeeded;
        }

        try {
            $response = $this->client->transaction($attempt->session_id);
        } catch (ConnectionException $exception) {
            $this->alerts->send('p24-state', 'Przelewy24 nie odpowiedziało o płatności', $order->number.': '.$exception->getMessage());

            return PaymentState::Pending;
        }

        // Przelewy24 tells only about money that came in, never about a refusal, so anything but „payment made”
        // keeps waiting. Status 1 (paid, not yet verified) waits for the notification, which does the verify.
        return $response->successful() && (int) $response->json('data.status') === self::PAID
            ? PaymentState::Succeeded
            : PaymentState::Pending;
    }

    /**
     * $blikHere: the code comes from our own field and is charged straight after (BLIK Level 0).
     */
    private function register(Order $order, bool $blikHere): ?PaymentAttempt
    {
        $sessionId = $order->number.'-'.Str::lower(Str::random(8));
        // Amount vouchers paid part of it already (MA-211); the gateway takes the rest.
        $amount = $order->amountDue();
        $currency = $order->currency ?? 'PLN';

        $body = [
            'merchantId' => $this->client->merchantId(),
            'posId' => $this->client->posId(),
            'sessionId' => $sessionId,
            'amount' => $amount,
            'currency' => $currency,
            // The bank shows this line, so it says who takes the money and for which order.
            'description' => 'MellowAura '.$order->number,
            'email' => $order->email,
            'client' => Str::limit((string) $order->name, 40, ''),
            'country' => 'PL',
            'language' => $order->locale === 'en' ? 'en' : 'pl',
            // Signed with the order's number: the bank's app often opens it in another browser, without our session.
            'urlReturn' => $order->confirmationUrl(),
            'urlStatus' => route('payments.przelewy24.notify'),
            'sign' => $this->client->sign([
                'sessionId' => $sessionId,
                'merchantId' => $this->client->merchantId(),
                'amount' => $amount,
                'currency' => $currency,
            ]),
        ];

        // BLIK charged by code needs the customer's address and browser (the PSU object), says the documentation.
        // BLIK on Przelewy24's page opens with BLIK already chosen, so the customer sees no list of banks.
        if ($blikHere) {
            $body['additional'] = ['PSU' => ['IP' => (string) request()->ip(), 'userAgent' => Str::limit((string) request()->userAgent(), 255, '')]];
        } elseif ($order->payment_method === PaymentMethod::Blik) {
            $body['method'] = self::BLIK_METHOD;
        }

        try {
            $response = $this->client->register($body);
        } catch (ConnectionException $exception) {
            $this->alerts->send('p24-register', 'Przelewy24 nie odpowiada', $order->number.': '.$exception->getMessage());

            return null;
        }

        $token = $response->json('data.token');

        if (! $response->successful() || blank($token)) {
            $this->alerts->send('p24-register', 'Przelewy24 nie przyjęło płatności', $order->number.': HTTP '.$response->status().' '.Str::limit($response->body(), 300));

            return null;
        }

        ($this->attachReference)($order, $sessionId);

        return PaymentAttempt::create([
            'order_id' => $order->id,
            'provider' => self::PROVIDER,
            'session_id' => $sessionId,
            'amount' => $amount,
            'currency' => $currency,
            'token' => (string) $token,
        ]);
    }

    private function charge(Order $order, PaymentAttempt $attempt, string $blikCode): StartedPayment
    {
        try {
            $response = $this->client->chargeBlik((string) $attempt->token, $blikCode);
        } catch (ConnectionException $exception) {
            $attempt->update(['status' => PaymentAttempt::FAILED]);
            $this->alerts->send('p24-blik', 'Przelewy24 nie odpowiada przy BLIK-u', $order->number.': '.$exception->getMessage());

            return StartedPayment::rejected(__('payments::gateway.down'));
        }

        if (! $response->successful()) {
            $attempt->update(['status' => PaymentAttempt::FAILED]);

            // 400 is a code the bank or Przelewy24 did not take (wrong, expired, used); anything else is on their side.
            if ($response->status() !== 400) {
                $this->alerts->send('p24-blik', 'Przelewy24 nie obciążyło BLIK-a', $order->number.': HTTP '.$response->status().' '.Str::limit($response->body(), 300));
            }

            return StartedPayment::rejected(__($response->status() === 400 ? 'payments::gateway.code_refused' : 'payments::gateway.down'));
        }

        $attempt->update(['provider_order_id' => $response->json('data.orderId')]);

        // The customer now confirms in the bank's app; the notification says when the money is in.
        return StartedPayment::awaitingBank();
    }
}
