<?php

namespace App\Modules\Monitoring\Support;

use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Mails that did not go out after every attempt, read from the queue's failed jobs. The queue keeps each mail's
 * description and recipients next to the job (MonitoringServiceProvider), so the list never has to rebuild the mail.
 */
class FailedMails
{
    public function __construct(private FailedJobProviderInterface $failer) {}

    /**
     * Newest first.
     *
     * @return Collection<int, array{id: string, description: string, to: list<string>, failedAt: Carbon, reason: string}>
     */
    public function all(): Collection
    {
        return collect($this->failer->all())
            ->map(function (object $job): ?array {
                $mail = json_decode($job->payload, true)['mail'] ?? null;

                return $mail === null ? null : [
                    'id' => (string) $job->id,
                    'description' => (string) $mail['description'],
                    'to' => array_values($mail['to'] ?? []),
                    'failedAt' => Carbon::parse($job->failed_at),
                    'reason' => Str::limit(Str::before((string) $job->exception, "\n"), 300),
                ];
            })
            ->filter()
            ->values();
    }

    public function has(string $id): bool
    {
        return $this->all()->contains('id', $id);
    }
}
