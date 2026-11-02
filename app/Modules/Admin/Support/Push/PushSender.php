<?php

namespace App\Modules\Admin\Support\Push;

use App\Modules\Admin\Models\PushSubscription;
use Illuminate\Support\Collection;

/**
 * Hands an encrypted message to the push services of Apple, Google or Mozilla, which wake the phone.
 * Tests bind a fake in its place.
 */
interface PushSender
{
    /** The push service took the message. */
    public const SENT = 'sent';

    /** The device unsubscribed or was reset (404, 410): its row goes. */
    public const GONE = 'gone';

    /** Anything else — no connection, a busy service; worth one more try later. */
    public const FAILED = 'failed';

    /**
     * @param  Collection<int, PushSubscription>  $subscriptions
     * @param  array<string, string>  $payload
     * @return array<int, self::SENT|self::GONE|self::FAILED> the outcome for each subscription's id
     */
    public function send(Collection $subscriptions, array $payload): array;
}
