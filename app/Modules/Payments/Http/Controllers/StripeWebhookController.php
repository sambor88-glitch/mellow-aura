<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Payments\Models\PaymentEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * What Stripe tells us about a payment. This is the only word that counts: the browser can be closed,
 * lie or never come back, so the shelf and the confirmation mail wait for this call.
 *
 * Every verified call is written to payment_events, and the same call arriving twice is handled once.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private Alerts $alerts) {}

    public function __invoke(Request $request, MarkOrderPaid $markOrderPaid): Response
    {
        $secret = (string) config('services.stripe.webhook_secret');

        // No secret yet: there is nothing to check a signature against, so the address does not exist.
        abort_if(blank($secret), 404);

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (SignatureVerificationException|UnexpectedValueException $exception) {
            // Not written down: a call we cannot verify says nothing we may believe.
            $this->alerts->send('stripe-webhook-signature', 'Webhook Stripe z niepoprawnym podpisem', $exception->getMessage());

            return response('', 400);
        }

        $intent = $event->data->object ?? null;
        $intentId = is_object($intent) && isset($intent->id) ? (string) $intent->id : null;
        $order = $intentId === null ? null : Order::query()->where('payment_provider_id', $intentId)->first();

        $record = PaymentEvent::firstOrCreate(['event_id' => $event->id], [
            'type' => $event->type,
            'payment_provider_id' => $intentId,
            'order_id' => $order?->id,
            'payload' => $event->toArray(),
        ]);

        // Stripe repeats a call until it gets an answer, and repeats after our own errors too.
        if ($record->handled_at !== null) {
            return response('', 204);
        }

        match ($event->type) {
            'payment_intent.succeeded' => $this->paid($record, $order, (int) ($intent->amount ?? 0), strtoupper((string) ($intent->currency ?? '')), (string) $intentId, $markOrderPaid),
            'payment_intent.payment_failed', 'payment_intent.canceled' => $this->failed($record, $order),
            default => $record->handled('Zdarzenie, którego sklep nie obsługuje'),
        };

        return response('', 204);
    }

    /**
     * Money is in. The shelf, the vouchers and the mails happen in MarkOrderPaid, which is safe to
     * call again — a call that arrives twice changes nothing the second time.
     */
    private function paid(PaymentEvent $record, ?Order $order, int $amount, string $currency, string $intentId, MarkOrderPaid $markOrderPaid): void
    {
        if ($order === null) {
            $record->handled('Płatność bez zamówienia w sklepie');
            $this->alerts->send('stripe-orphan-payment', 'Płatność bez zamówienia', 'Stripe potwierdził płatność '.$intentId.', a w sklepie nie ma zamówienia z tym numerem.');

            return;
        }

        // A sum other than the one on the order is never quietly accepted — nor the same number in another currency.
        if ($amount !== $order->total_gross || $currency !== $order->currency) {
            $paid = $amount.' '.$currency;
            $due = $order->total_gross.' '.$order->currency;
            $record->handled('Kwota '.$paid.' inna niż w zamówieniu ('.$due.', w groszach albo centach)');
            $this->alerts->send('stripe-amount', 'Zapłacono inną kwotę', $order->number.': Stripe mówi o '.$paid.', zamówienie ma '.$due.' (w groszach albo centach). Zamówienie zostaje nieopłacone.');

            return;
        }

        $markOrderPaid($order, $intentId);

        $record->handled('Zamówienie '.$order->number.' opłacone');
    }

    private function failed(PaymentEvent $record, ?Order $order): void
    {
        // A refusal never touches an order that is already paid: the customer may have paid at the second try.
        if ($order === null || $order->payment_status === PaymentStatus::Paid) {
            $record->handled($order === null ? 'Nieudana płatność bez zamówienia w sklepie' : 'Zamówienie było już opłacone');

            return;
        }

        $order->update(['payment_status' => PaymentStatus::Failed]);

        $record->handled('Nieudana płatność zamówienia '.$order->number);
    }
}
