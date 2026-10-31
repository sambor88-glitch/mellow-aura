<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Checkout\Models\Order;
use App\Modules\Consent\Support\Consent;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * A paid order told to Google Analytics from the server, through the Measurement Protocol (MA-264). The browser on
 * the confirmation page sends the same purchase, but a redirect to Przelewy24 or an ad blocker loses some of them;
 * Analytics counts one purchase per transaction_id, so the order number keeps the two from counting twice.
 *
 * Off without the API secret in .env or the measurement ID in the panel, and for an order placed without a yes
 * to statistics. The hashed e-mail for Google Ads (enhanced conversions) goes only with the ad_user_data consent.
 * The EU endpoint, so the data are collected in the European Union.
 */
class MeasurementProtocol
{
    public const ENDPOINT = 'https://region1.google-analytics.com/mp/collect';

    public function __construct(private Consent $consent) {}

    public function isEnabled(): bool
    {
        return filled(config('services.ga4.api_secret')) && $this->consent->measurementId() !== null;
    }

    /**
     * The order may be told: Analytics is on and the customer said yes when ordering. An order worth nothing — a mug
     * chosen with a gift choice card, paid for by whoever bought the card — is no sale and stays out.
     */
    public function covers(Order $order): bool
    {
        return $this->isEnabled() && filled($order->ga_client_id) && $order->total_gross > 0;
    }

    /**
     * @throws RequestException when Google answers with an error, so the queue tries again
     */
    public function sendPurchase(Order $order): void
    {
        Http::timeout(10)
            ->withQueryParameters(['measurement_id' => $this->consent->measurementId(), 'api_secret' => config('services.ga4.api_secret')])
            ->post(self::ENDPOINT, $this->payload($order))
            ->throw();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Order $order): array
    {
        $adUserData = $order->analytics_ad_user_data;

        $params = array_filter([
            'currency' => $order->currency,
            // The worth of the items without delivery, the way the confirmation page counts it in the browser.
            'value' => ($order->total_gross - $order->shipping_gross) / 100,
            'transaction_id' => $order->number,
            'shipping' => $order->shipping_gross / 100,
            'session_id' => $order->ga_session_id,
            // A session id counts only with engagement time; one millisecond says the purchase belongs to that visit.
            'engagement_time_msec' => $order->ga_session_id !== null ? 1 : null,
            // Staging's test purchases show in DebugView and stay out of the reports.
            'debug_mode' => app()->isProduction() ? null : true,
            'items' => $order->analytics_items ?? [],
        ], fn (mixed $value) => $value !== null);

        return array_filter([
            'client_id' => $order->ga_client_id,
            'timestamp_micros' => ($order->paid_at ?? now())->getTimestampMs() * 1000,
            'consent' => [
                'ad_user_data' => $adUserData ? 'GRANTED' : 'DENIED',
                'ad_personalization' => 'DENIED',
            ],
            'user_data' => $adUserData ? ['sha256_email_address' => [self::hashEmail($order->email)]] : null,
            'events' => [['name' => 'purchase', 'params' => $params]],
        ], fn (mixed $value) => $value !== null);
    }

    /**
     * Google's normalization before SHA-256: trimmed, lower case, and for Gmail without the dots before the @.
     */
    public static function hashEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $email = str_replace('.', '', $local).'@'.$domain;
        }

        return hash('sha256', $email);
    }
}
