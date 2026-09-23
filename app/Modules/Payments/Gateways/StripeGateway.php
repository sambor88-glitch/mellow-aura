<?php

namespace App\Modules\Payments\Gateways;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Enums\PaymentState;
use App\Modules\Payments\Support\StartedPayment;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Stripe. BLIK, card and Przelewy24 go through one payment intent in the currency of the order; the customer
 * confirms it in the browser, so the card number and the BLIK code never touch our server.
 *
 * A bank transfer has nothing to confirm online: the order waits for the money and the customer gets
 * the account number by mail.
 */
class StripeGateway implements PaymentGateway
{
    /**
     * What Stripe calls each way of paying. The card is typed into Stripe's own field on the checkout page.
     */
    private const METHODS = [
        PaymentMethod::Blik->value => 'blik',
        PaymentMethod::OnlineTransfer->value => 'p24',
        PaymentMethod::Card->value => 'card',
    ];

    public function __construct(private StripeClient $stripe, private Alerts $alerts) {}

    public function start(Order $order, ?string $blikCode): StartedPayment
    {
        // A bank transfer has nothing to confirm online: the order waits for the money and the
        // customer gets the account number by mail.
        if ($order->payment_method === PaymentMethod::BankTransfer) {
            return StartedPayment::done();
        }

        $method = self::METHODS[$order->payment_method->value] ?? null;

        // Never „done” for a way of paying we cannot take — that would leave an order the shop
        // treats as placed and nobody ever pays for.
        if ($method === null) {
            return StartedPayment::rejected(__('payments::gateway.unsupported'));
        }

        try {
            $intent = $this->stripe->paymentIntents->create([
                // Grosze or cents, exactly as the order keeps them, in the currency it was placed in.
                'amount' => $order->total_gross,
                'currency' => strtolower($order->currency ?? 'PLN'),
                'payment_method_types' => [$method],
                // The bank application shows this line, so it says who is taking the money and for which order.
                'description' => 'MellowAura '.$order->number,
                'metadata' => ['order' => $order->number],
            ]);
        } catch (ApiErrorException $exception) {
            $this->alerts->send('stripe-intent', 'Stripe nie przyjął płatności', $order->number.': '.$exception->getMessage());

            return StartedPayment::rejected(__('payments::gateway.down'));
        }

        $order->update(['payment_provider_id' => $intent->id]);

        return StartedPayment::confirmInBrowser($intent->client_secret);
    }

    public function state(Order $order): PaymentState
    {
        if (blank($order->payment_provider_id)) {
            return PaymentState::Pending;
        }

        try {
            $intent = $this->stripe->paymentIntents->retrieve($order->payment_provider_id);
        } catch (ApiErrorException $exception) {
            $this->alerts->send('stripe-retrieve', 'Stripe nie odpowiedział o płatności', $order->number.': '.$exception->getMessage());

            return PaymentState::Pending;
        }

        // Only „canceled” is a final no. Everything else that is not money in keeps waiting, because an
        // order wrongly marked as failed looks to Kasia like a customer who never paid.
        return match ($intent->status) {
            'succeeded' => PaymentState::Succeeded,
            'canceled' => PaymentState::Failed,
            default => PaymentState::Pending,
        };
    }
}
