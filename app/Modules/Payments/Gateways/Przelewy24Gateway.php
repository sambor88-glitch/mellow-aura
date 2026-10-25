<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Przelewy24\Client;
use App\Modules\Payments\Przelewy24\Przelewy24Exception;
use App\Modules\Payments\Support\StartedPayment;
use Illuminate\Support\Str;

/**
 * Przelewy24. BLIK, card and online transfer are one registered transaction in the currency of the order;
 * the customer pays on Przelewy24's page, so the card number never touches our server.
 *
 * For now BLIK goes to that page too and the code is typed there. Charging the code from our own field
 * comes with MA-101, against the same token.
 */
class Przelewy24Gateway implements PaymentGateway
{
    /**
     * Where Przelewy24 sends its word on the payment. The route itself and its checks come with MA-102.
     */
    public const STATUS_PATH = '/przelewy24/status';

    public function __construct(private Client $client, private Alerts $alerts) {}

    public function start(Order $order, ?string $blikCode): StartedPayment
    {
        // A bank transfer has nothing to confirm online: the order waits for the money and the
        // customer gets the account number by mail.
        if ($order->payment_method === PaymentMethod::BankTransfer) {
            return StartedPayment::done();
        }

        // Written before Przelewy24 hears of it, so the amount we expect is on record first.
        $payment = Payment::create([
            'order_id' => $order->id,
            'session_id' => $order->number.'-'.Str::lower(Str::random(10)),
            'amount' => $order->total_gross,
            'currency' => $order->currency ?? 'PLN',
            'method' => $order->payment_method,
            'status' => PaymentState::Pending,
        ]);

        try {
            $token = $this->client->register([
                'sessionId' => $payment->session_id,
                // Grosze or cents, exactly as the order keeps them, in the currency it was placed in.
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                // The bank application shows this line, so it says who is taking the money and for which order.
                'description' => 'MellowAura '.$order->number,
                'email' => $order->email,
                'country' => 'PL',
                'language' => $order->locale === 'en' ? 'en' : 'pl',
                'urlReturn' => route('checkout.confirmation'),
                'urlStatus' => url(self::STATUS_PATH),
            ]);
        } catch (Przelewy24Exception $exception) {
            $payment->update(['status' => PaymentState::Failed]);
            $this->alerts->send('p24-register', 'Przelewy24 nie przyjęło płatności', $order->number.': '.$exception->getMessage());

            return StartedPayment::rejected(__('payments::gateway.down'));
        }

        $payment->update(['token' => $token]);

        return StartedPayment::redirect($this->client->redirectUrl($token));
    }

    /**
     * Only Przelewy24's notification, verified, marks a payment as paid (MA-102). Until then the latest
     * attempt says what we know, and „still going” is the honest default.
     */
    public function state(Order $order): PaymentState
    {
        $payment = Payment::query()->where('order_id', $order->id)->latest('id')->first();

        return $payment?->status ?? PaymentState::Pending;
    }
}
