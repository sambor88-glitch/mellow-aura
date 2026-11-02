<?php

namespace App\Modules\Admin\Jobs;

use App\Modules\Admin\Models\PushSubscription;
use App\Modules\Admin\Support\PanelAccess;
use App\Modules\Admin\Support\Push\PanelNotice;
use App\Modules\Admin\Support\Push\PushSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Sends one notification to every device of the accounts that may open its screen (MA-345).
 * A device the push service no longer knows (404, 410) is removed. A device that could not be reached gets
 * one more try a minute later — only that device, so the others don't ring twice.
 * A notification that never arrives is only logged: Kasia gets the same news by e-mail.
 */
class SendPanelPush implements ShouldQueue
{
    use Queueable;

    private const ROUNDS = 3;

    /**
     * @param  ?list<int>  $subscriptionIds  only these devices, on a later round
     */
    public function __construct(public PanelNotice $notice, public ?array $subscriptionIds = null, public int $round = 1) {}

    public function handle(PushSender $sender): void
    {
        $subscriptions = PushSubscription::query()
            ->with('user')
            ->when($this->subscriptionIds !== null, fn ($query) => $query->whereKey($this->subscriptionIds))
            ->when($this->notice->onlyUser !== null, fn ($query) => $query->where('user_id', $this->notice->onlyUser))
            ->get()
            ->filter(fn (PushSubscription $subscription) => PanelAccess::allows($subscription->user, $this->notice->route))
            ->values();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $outcomes = $sender->send($subscriptions, $this->notice->payload());

        $sent = array_keys($outcomes, PushSender::SENT, true);
        $gone = array_keys($outcomes, PushSender::GONE, true);
        $failed = array_keys($outcomes, PushSender::FAILED, true);

        if ($sent !== []) {
            PushSubscription::query()->whereKey($sent)->update(['last_sent_at' => now()]);
        }

        if ($gone !== []) {
            PushSubscription::query()->whereKey($gone)->delete();
        }

        if ($failed === []) {
            return;
        }

        if ($this->round < self::ROUNDS) {
            self::dispatch($this->notice, $failed, $this->round + 1)->delay(now()->addMinutes($this->round));

            return;
        }

        Log::warning('Powiadomienie z panelu nie doszło do '.count($failed).' urządzeń po '.self::ROUNDS.' próbach: '.$this->notice->tag.'.');
    }
}
