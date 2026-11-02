<?php

namespace App\Modules\Admin\Support\Push;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Web Push with the VAPID keys from .env: the message encrypted for each device and signed with the shop's key
 * (WebPush), all devices at once. The push service answers 201; 404 and 410 mean the device is gone.
 */
class WebPushSender implements PushSender
{
    /** A notification about a new order is no use hours later. */
    private const TTL = 4 * 3600;

    public function send(Collection $subscriptions, array $payload): array
    {
        $message = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $public = (string) config('services.webpush.public_key');
        $private = (string) config('services.webpush.private_key');
        $subject = (string) config('services.webpush.subject');

        $requests = [];
        $outcomes = [];

        foreach ($subscriptions as $subscription) {
            try {
                $requests[$subscription->id] = [
                    'url' => $subscription->endpoint,
                    'body' => WebPush::encrypt($message, $subscription->public_key, $subscription->auth_token),
                    'authorization' => WebPush::authorization($subscription->endpoint, $public, $private, $subject),
                ];
            } catch (Throwable $broken) {
                // Keys the browser gave can't be used: the device has to turn the notifications on again.
                report($broken);
                $outcomes[$subscription->id] = self::GONE;
            }
        }

        if ($requests === []) {
            return $outcomes;
        }

        $responses = Http::pool(fn (Pool $pool) => collect($requests)->map(fn (array $request, int $id) => $pool->as((string) $id)
            ->timeout(15)
            ->withHeaders([
                'Authorization' => $request['authorization'],
                'Content-Encoding' => 'aes128gcm',
                'TTL' => (string) self::TTL,
                'Urgency' => 'high',
            ])
            ->withBody($request['body'], 'application/octet-stream')
            ->post($request['url']))->values()->all());

        foreach ($responses as $id => $response) {
            $outcomes[(int) $id] = match (true) {
                ! $response instanceof Response => self::FAILED,
                $response->successful() => self::SENT,
                in_array($response->status(), [404, 410], true) => self::GONE,
                default => self::FAILED,
            };
        }

        return $outcomes;
    }
}
