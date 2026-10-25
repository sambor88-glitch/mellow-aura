<?php

namespace App\Modules\Monitoring\Console;

use App\Modules\Monitoring\Support\Alerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Backup\BackupDestination\BackupDestination;
use Throwable;
use ZipArchive;

/**
 * The weekly restore drill. A backup nobody has ever restored is a hope, not a backup: this takes the newest
 * archive from the bucket, opens it with the archive password, loads the database dump into an empty database
 * of its own and checks that the shop's key tables came back and that every photo in the media table is in the archive.
 *
 * The restored copy holds customers' data, so its tables are dropped again as soon as the check is over.
 */
class VerifyBackupCommand extends Command
{
    /** Tables without which the shop cannot be rebuilt. Each must come back with rows if the shop has rows in it. */
    public const KEY_TABLES = ['settings', 'categories', 'products', 'product_variants', 'orders', 'order_items', 'media', 'users'];

    /** Media library disks and where their files sit in the archive (relative_path is storage/app). */
    private const DISK_FOLDERS = ['public' => 'public/', 'local' => 'private/'];

    protected $signature = 'monitoring:verify-backup';

    protected $description = 'Odtwarza najnowszą kopię zapasową w osobnej bazie i sprawdza bazę i zdjęcia';

    public function handle(Alerts $alerts): int
    {
        $archive = null;

        try {
            $archive = $this->download();
            $report = $this->verify($archive);
        } catch (Throwable $exception) {
            $alerts->send(
                key: 'backup-restore',
                subject: 'Próba odtworzenia kopii zapasowej nieudana',
                details: $exception->getMessage()
                    ."\n\nKopia może być niepełna albo nie da się jej otworzyć. Sprawdź `php artisan backup:list` i `php artisan monitoring:verify-backup` na serwerze.",
            );
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($archive !== null) {
                @unlink($archive);
            }

            $this->dropRestoredTables();
        }

        $this->info($report);

        return self::SUCCESS;
    }

    /**
     * The newest archive of this environment, copied to a temporary file.
     */
    private function download(): string
    {
        $disk = (string) config('backup.backup.destination.disks.0');
        $backup = BackupDestination::create($disk, (string) config('backup.backup.name'))->newestBackup();

        if ($backup === null) {
            throw new RuntimeException('Na dysku „'.$disk.'” nie ma żadnej kopii zapasowej.');
        }

        $path = tempnam(sys_get_temp_dir(), 'backup-check-');
        $target = fopen($path, 'wb');
        stream_copy_to_stream($backup->stream(), $target);
        fclose($target);

        return $path;
    }

    private function verify(string $archive): string
    {
        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Archiwum kopii nie daje się otworzyć.');
        }

        if (filled($password = config('backup.backup.password'))) {
            $zip->setPassword($password);
        }

        $entries = [];
        $dump = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $entries[$name] = true;

            if (str_starts_with($name, 'db-dumps/') && str_ends_with($name, '.sql')) {
                $dump = $name;
            }
        }

        if ($dump === null) {
            throw new RuntimeException('W archiwum nie ma zrzutu bazy (db-dumps/*.sql).');
        }

        $sql = $zip->getFromName($dump);
        $zip->close();

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Zrzut bazy '.$dump.' jest pusty albo hasło archiwum się nie zgadza.');
        }

        $this->restore($sql);

        $tables = $this->checkTables();
        $photos = $this->checkPhotos($entries);

        return 'Kopia odtworzona: '.$tables.', zdjęć w archiwum: '.$photos.'.';
    }

    private function restore(string $sql): void
    {
        if (blank(config('database.connections.restore_check.database'))) {
            throw new RuntimeException('Brak bazy do próby odtworzenia: ustaw DB_RESTORE_DATABASE (pusta baza obok bazy sklepu).');
        }

        if (! self::restoreDatabaseIsSeparate()) {
            throw new RuntimeException('DB_RESTORE_DATABASE wskazuje bazę sklepu. Próba odtworzenia potrzebuje osobnej, pustej bazy.');
        }

        $this->dropRestoredTables();
        DB::connection('restore_check')->unprepared($sql);
    }

    /**
     * The drill wipes the database it loads into, so every drop asks this first: set, and not the shop's own.
     */
    public static function restoreDatabaseIsSeparate(): bool
    {
        $restore = config('database.connections.restore_check');
        $shop = config('database.connections.'.config('database.default'));

        if (blank($restore['database'] ?? null)) {
            return false;
        }

        // SQLite names a file, and two spellings of one path are the same database.
        $name = fn (array $connection) => $connection['driver'] === 'sqlite'
            ? (realpath((string) $connection['database']) ?: (string) $connection['database'])
            : $connection['host'].'/'.$connection['database'];

        return $restore['driver'] !== $shop['driver'] || $name($restore) !== $name($shop);
    }

    private function checkTables(): string
    {
        $restored = DB::connection('restore_check');
        $counts = [];

        foreach (self::KEY_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::connection('restore_check')->hasTable($table)) {
                throw new RuntimeException('Po odtworzeniu brakuje tabeli '.$table.'.');
            }

            $count = $restored->table($table)->count();

            if ($count === 0 && DB::table($table)->exists()) {
                throw new RuntimeException('Tabela '.$table.' wróciła pusta, a w sklepie ma wiersze.');
            }

            $counts[] = $table.' '.$count;
        }

        return implode(', ', $counts);
    }

    /**
     * Every original photo the restored media table points to must be in the archive; generated sizes can be rebuilt.
     *
     * @param  array<string, true>  $entries
     */
    private function checkPhotos(array $entries): int
    {
        if (! Schema::connection('restore_check')->hasTable('media')) {
            return 0;
        }

        $missing = [];
        $found = 0;

        foreach (DB::connection('restore_check')->table('media')->get(['id', 'disk', 'file_name']) as $media) {
            $folder = self::DISK_FOLDERS[$media->disk] ?? null;

            if ($folder === null) {
                continue;
            }

            isset($entries[$folder.$media->id.'/'.$media->file_name]) ? $found++ : $missing[] = $media->id.'/'.$media->file_name;
        }

        if ($missing !== []) {
            throw new RuntimeException('W archiwum brakuje '.count($missing).' zdjęć z bazy, np. '.implode(', ', array_slice($missing, 0, 5)).'.');
        }

        return $found;
    }

    private function dropRestoredTables(): void
    {
        if (! self::restoreDatabaseIsSeparate()) {
            return;
        }

        rescue(fn () => Schema::connection('restore_check')->dropAllTables(), report: false);
    }
}
