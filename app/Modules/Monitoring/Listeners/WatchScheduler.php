<?php

namespace App\Modules\Monitoring\Listeners;

use App\Modules\Monitoring\Models\Heartbeat;
use App\Modules\Monitoring\Support\Alerts;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The queue worker's half of the watch. Between jobs it leaves its own sign of life and checks the scheduler's,
 * at most once a minute. It never returns a value: a Looping listener that returns false pauses the worker.
 */
class WatchScheduler
{
    public function __construct(private Alerts $alerts) {}

    public function handle(Looping $event): void
    {
        if (! Cache::add('monitoring:worker-looked', true, 60)) {
            return;
        }

        try {
            Heartbeat::record(Heartbeat::QUEUE);

            $silentFor = Heartbeat::silentFor(Heartbeat::SCHEDULER);

            if ($silentFor !== null && $silentFor >= (int) config('monitoring.silence_minutes')) {
                $this->alerts->send(
                    key: 'scheduler-silent',
                    subject: 'Harmonogram nie działa od '.$silentFor.' min',
                    details: 'schedule:run nie uruchomił się od '.$silentFor.' min. Bez harmonogramu nie odświeżają się zdjęcia z Instagrama, nie czyści się lista nieudanych zadań i nikt nie pilnuje kolejki.'
                        ."\n\nSprawdź zadanie harmonogramu na Forge (serwer → Scheduler).",
                );
            }
        } catch (Throwable $exception) {
            // A database that is away for a moment must not stop the worker.
            Log::warning('Queue worker could not check the scheduler: '.$exception->getMessage());
        }
    }
}
