<?php

namespace App\Modules\Monitoring;

use App\Modules\Monitoring\Console\CheckQueueCommand;
use App\Modules\Monitoring\Console\VerifyBackupCommand;
use App\Modules\Monitoring\Listeners\ReportBackupProblem;
use App\Modules\Monitoring\Listeners\ReportFailedJob;
use App\Modules\Monitoring\Listeners\WatchScheduler;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Shared\Mail\QueuedMail;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Event;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Throwable;

/**
 * Keeps an eye on what runs apart from the page views: the queue worker that sends the mails and the scheduler.
 * They watch each other, errors and mails that did not go out raise an alert, and Kasia sees those mails in the panel.
 */
class MonitoringServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $heartbeatUrl = (string) config('monitoring.heartbeat_url');

            // The scheduler's sign of life and the watch over the queue worker. It keeps running during maintenance,
            // so the worker never mistakes a maintenance break for a stopped scheduler.
            $schedule->command(CheckQueueCommand::class)->everyMinute()->evenInMaintenanceMode()->pingOnSuccessIf($heartbeatUrl !== '', $heartbeatUrl);

            // A failed job keeps the mail's recipients and content, so it goes after 30 days, like the server logs.
            $schedule->command('queue:prune-failed', ['--hours' => 24 * 30])->daily();

            // Backups at night, when nobody shops: old archives go first, so the new one always has room.
            // The morning check raises an alert if last night's archive is missing; the drill restores one every week.
            $schedule->command('backup:clean')->dailyAt('02:40')->withoutOverlapping();
            $schedule->command('backup:run')->dailyAt('02:50')->withoutOverlapping();
            $schedule->command('backup:monitor')->dailyAt('08:50');
            $schedule->command(VerifyBackupCommand::class)->weeklyOn(1, '04:20')->withoutOverlapping();
        });

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler) {
            if ($handler instanceof Handler) {
                $handler->reportable(function (Throwable $exception): void {
                    $this->app->make(Alerts::class)->exception($exception);
                });
            }
        });
    }

    public function boot(): void
    {
        parent::boot();

        // What the mail is and to whom, next to the job, for the panel's list when it does not go out.
        Queue::createPayloadUsing(static function (?string $connection, ?string $queue, array $payload): array {
            $job = $payload['data']['command'] ?? null;

            return $job instanceof SendQueuedMailable && $job->mailable instanceof QueuedMail
                ? ['mail' => ['description' => $job->mailable->description(), 'to' => array_column($job->mailable->to, 'address')]]
                : [];
        });

        Event::listen(JobFailed::class, ReportFailedJob::class);
        Event::listen(Looping::class, WatchScheduler::class);
        Event::listen([BackupHasFailed::class, CleanupHasFailed::class, UnhealthyBackupWasFound::class], ReportBackupProblem::class);

        if ($this->app->runningInConsole()) {
            $this->commands([CheckQueueCommand::class, VerifyBackupCommand::class]);
        }
    }
}
