<?php

namespace App\Modules\Monitoring\Listeners;

use App\Modules\Monitoring\Support\Alerts;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;

/**
 * A job that failed for the last time. A mail also waits on Kasia's list in the panel; the alert says which one
 * and why, once an hour per kind of job.
 */
class ReportFailedJob
{
    public function __construct(private Alerts $alerts) {}

    public function handle(JobFailed $event): void
    {
        $payload = $event->job->payload();
        $mail = $payload['mail'] ?? null;
        $name = $mail['description'] ?? $event->job->resolveName();

        $this->alerts->send(
            key: 'job-failed:'.$event->job->resolveName(),
            subject: ($mail ? 'Mail nie wyszedł: ' : 'Zadanie w tle nie powiodło się: ').$name,
            details: implode("\n", array_filter([
                $mail ? 'Do: '.implode(', ', $mail['to'] ?? []) : null,
                'Prób: '.$event->job->attempts(),
                'Powód: '.Str::limit($event->exception->getMessage(), 500),
                $mail ? "\nMail czeka w panelu: ".route('admin.failed-mails.index').' — stamtąd można go wysłać jeszcze raz.' : null,
            ])),
        );
    }
}
