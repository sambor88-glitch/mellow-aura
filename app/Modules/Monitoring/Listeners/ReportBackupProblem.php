<?php

namespace App\Modules\Monitoring\Listeners;

use App\Modules\Monitoring\Support\Alerts;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

/**
 * A nightly backup that failed, a cleanup that failed, or a backup the morning check finds too old or too big.
 * Each goes out like every other technical alert, instead of the backup package's own mails.
 */
class ReportBackupProblem
{
    public function __construct(private Alerts $alerts) {}

    public function handle(BackupHasFailed|CleanupHasFailed|UnhealthyBackupWasFound $event): void
    {
        $where = 'Dysk: '.($event->diskName ?? '—').', kopia: '.($event->backupName ?? '—').'.';

        match (true) {
            $event instanceof BackupHasFailed => $this->alerts->send(
                key: 'backup-failed',
                subject: 'Nocna kopia zapasowa nieudana',
                details: $event->exception->getMessage()."\n\n".$where.' Sprawdź storage/logs/laravel.log i zmienne BACKUP_* na Forge.',
            ),
            $event instanceof CleanupHasFailed => $this->alerts->send(
                key: 'backup-cleanup-failed',
                subject: 'Czyszczenie starych kopii zapasowych nieudane',
                details: $event->exception->getMessage()."\n\n".$where,
            ),
            default => $this->alerts->send(
                key: 'backup-unhealthy',
                subject: 'Kopia zapasowa jest za stara albo za duża',
                details: $event->failureMessages->map(fn (array $failure) => '– '.$failure['check'].': '.$failure['message'])->implode("\n")
                    ."\n\n".$where.' Nocna kopia prawdopodobnie się nie zrobiła.',
            ),
        };
    }
}
