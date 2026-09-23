<?php

namespace App\Modules\Monitoring\Console;

use App\Modules\Monitoring\Models\Heartbeat;
use App\Modules\Monitoring\Support\Alerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Runs every minute from the scheduler: leaves the scheduler's sign of life and checks that the queue worker
 * is alive and keeps up. Mails wait in the queue, so a worker that stopped means nobody gets a confirmation.
 */
class CheckQueueCommand extends Command
{
    protected $signature = 'monitoring:check';

    protected $description = 'Zapisuje znak życia harmonogramu i alarmuje, gdy kolejka stoi';

    public function handle(Alerts $alerts): int
    {
        Heartbeat::record(Heartbeat::SCHEDULER);

        $connection = config('queue.connections.'.config('queue.default'));

        // Only a database queue has a worker to watch, and during maintenance the worker pauses on purpose.
        if (($connection['driver'] ?? null) !== 'database' || app()->isDownForMaintenance()) {
            return self::SUCCESS;
        }

        $limit = (int) config('monitoring.silence_minutes');
        $jobs = DB::connection($connection['connection'] ?? null)->table($connection['table'])->whereNull('reserved_at')->where('available_at', '<=', now()->timestamp);
        $waiting = (clone $jobs)->count();
        $oldest = (clone $jobs)->min('available_at');
        $waitingFor = $oldest === null ? 0 : intdiv(now()->timestamp - (int) $oldest, 60);
        $silentFor = Heartbeat::silentFor(Heartbeat::QUEUE);

        if ($silentFor !== null && $silentFor >= $limit) {
            $alerts->send(
                key: 'queue-silent',
                subject: 'Kolejka nie działa od '.$silentFor.' min',
                details: 'Proces queue:work nie dał znaku życia od '.$silentFor.' min. Zadań w kolejce: '.$waiting.' — to głównie maile do klientek, które czekają na wysłanie.'
                    ."\n\nSprawdź proces na Forge (serwer → Background processes) i storage/logs/laravel.log.",
            );
        } elseif ($waitingFor >= $limit) {
            $alerts->send(
                key: 'queue-backlog',
                subject: 'Zadanie czeka w kolejce od '.$waitingFor.' min',
                details: 'Najstarsze zadanie czeka od '.$waitingFor.' min, a w kolejce jest ich '.$waiting.'. Proces queue:work nie działa albo nie nadąża.'
                    ."\n\nSprawdź proces na Forge (serwer → Background processes) i storage/logs/laravel.log.",
            );
        }

        return self::SUCCESS;
    }
}
