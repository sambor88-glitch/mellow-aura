<?php

namespace App\Modules\Admin\Support\Push;

use App\Modules\Admin\Jobs\SendPanelPush;

/**
 * The panel's notifications on Kasia's phone (MA-345). A module calls notify() with its own words when something
 * new comes in; the message leaves from the queue, so a slow push service never holds up a payment or a form.
 * Without the VAPID keys in .env nothing is sent and the panel says the notifications wait for the server.
 */
class PanelPush
{
    public function enabled(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    public function publicKey(): ?string
    {
        return $this->enabled() ? (string) config('services.webpush.public_key') : null;
    }

    public function notify(PanelNotice $notice): void
    {
        if ($this->enabled()) {
            SendPanelPush::dispatch($notice)->afterCommit();
        }
    }
}
