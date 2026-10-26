<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Monitoring\Mail\Alert;
use App\Modules\Settings\Models\Setting;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;
use Tests\TestCase;
use ZipArchive;

/**
 * The nightly backup and the weekly restore drill. The archives here are built by hand, the way backup:run
 * lays them out: db-dumps/*.sql and the files of storage/app under public/ and private/, behind a password.
 */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $restoreFile;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
        config(['monitoring.alert_email' => 'opiekun@example.com']);

        // The drill loads into its own SQLite file, apart from the test database whatever its driver.
        $this->restoreFile = tempnam(sys_get_temp_dir(), 'restore-test-');
        config([
            'database.connections.restore_check' => ['driver' => 'sqlite', 'database' => $this->restoreFile, 'prefix' => '', 'foreign_key_constraints' => false],
            'backup.backup.name' => 'mellowaura-testing',
            'backup.backup.destination.disks' => ['local'],
            'backup.backup.password' => 'sekret',
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->restoreFile);

        parent::tearDown();
    }

    public function test_backups_run_every_night_and_are_restored_every_week(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->expression.' '.$event->command);

        $this->assertTrue($commands->contains(fn ($line) => str_starts_with($line, '50 2 * * *') && str_contains($line, 'backup:run')));
        $this->assertTrue($commands->contains(fn ($line) => str_starts_with($line, '40 2 * * *') && str_contains($line, 'backup:clean')));
        $this->assertTrue($commands->contains(fn ($line) => str_starts_with($line, '50 8 * * *') && str_contains($line, 'backup:monitor')));
        $this->assertTrue($commands->contains(fn ($line) => str_starts_with($line, '20 4 * * 1') && str_contains($line, 'monitoring:verify-backup')));
    }

    public function test_the_drill_restores_the_newest_archive_and_finds_every_photo(): void
    {
        Setting::create(['key' => 'contact_email', 'value' => 'x']);
        $this->archive('2026-10-18-02-50-00.zip', $this->dump(settings: 0), ['public/1/wazon.jpg' => 'jpg']);
        $this->archive('2026-10-25-02-50-00.zip', $this->dump(settings: 1), ['public/1/wazon.jpg' => 'jpg'], olderBy: 0);

        $this->assertSame(0, Artisan::call('monitoring:verify-backup'));
        $output = Artisan::output();
        $this->assertStringContainsString('settings 1,', $output);
        $this->assertStringContainsString('zdjęć w archiwum: 1.', $output);

        Mail::assertNothingSent();
        // The restored copy holds customers' data, so it is gone once the check is over.
        $this->assertSame([], Schema::connection('restore_check')->getTableListing());
    }

    public function test_a_photo_missing_from_the_archive_raises_an_alert(): void
    {
        $this->archive('2026-10-25-02-50-00.zip', $this->dump(settings: 0), []);

        $this->artisan('monitoring:verify-backup')->assertFailed();

        Mail::assertSent(Alert::class, fn (Alert $mail) => str_contains($mail->details, 'brakuje 1 zdjęć') && str_contains($mail->details, '1/wazon.jpg'));
    }

    public function test_a_wrong_archive_password_raises_an_alert(): void
    {
        $this->archive('2026-10-25-02-50-00.zip', $this->dump(settings: 0), ['public/1/wazon.jpg' => 'jpg']);
        config(['backup.backup.password' => 'inne']);

        $this->artisan('monitoring:verify-backup')->assertFailed();

        Mail::assertSent(Alert::class, fn (Alert $mail) => str_contains($mail->details, 'hasło archiwum się nie zgadza'));
    }

    public function test_no_archive_at_all_raises_an_alert(): void
    {
        $this->artisan('monitoring:verify-backup')->assertFailed();

        Mail::assertSent(Alert::class, fn (Alert $mail) => str_contains($mail->details, 'nie ma żadnej kopii'));
    }

    public function test_the_drill_never_touches_the_shops_own_database(): void
    {
        Setting::create(['key' => 'contact_email', 'value' => 'x']);
        $this->archive('2026-10-25-02-50-00.zip', $this->dump(settings: 1), ['public/1/wazon.jpg' => 'jpg']);
        config(['database.connections.restore_check' => config('database.connections.'.config('database.default'))]);

        $this->artisan('monitoring:verify-backup')->assertFailed();

        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertSame(1, DB::table('settings')->count());
        Mail::assertSent(Alert::class, fn (Alert $mail) => str_contains($mail->details, 'wskazuje bazę sklepu'));
    }

    public function test_a_failed_or_stale_backup_raises_an_alert_instead_of_the_packages_mail(): void
    {
        event(new BackupHasFailed(new Exception('mysqldump: Access denied'), 'backups', 'mellowaura-production'));
        event(new UnhealthyBackupWasFound('backups', 'mellowaura-production', collect([['check' => 'MaximumAgeInDays', 'message' => 'The latest backup is too old']])));

        Mail::assertSent(Alert::class, fn (Alert $mail) => str_contains($mail->details, 'mysqldump: Access denied'));
        Mail::assertSent(Alert::class, fn (Alert $mail) => str_contains($mail->details, 'MaximumAgeInDays: The latest backup is too old'));
    }

    /**
     * A dump with the drill's key tables, written so both SQLite and MySQL read it.
     */
    private function dump(int $settings): string
    {
        $sql = "CREATE TABLE settings (id INTEGER PRIMARY KEY, `key` VARCHAR(255));\n";
        $sql .= "CREATE TABLE media (id INTEGER PRIMARY KEY, disk VARCHAR(255), file_name VARCHAR(255));\n";
        $sql .= "INSERT INTO media VALUES (1, 'public', 'wazon.jpg');\n";

        foreach (range(1, $settings) as $id) {
            $sql .= $settings > 0 ? "INSERT INTO settings VALUES ({$id}, 'contact_email');\n" : '';
        }

        foreach (['categories', 'products', 'product_variants', 'orders', 'order_items', 'users'] as $table) {
            $sql .= "CREATE TABLE {$table} (id INTEGER PRIMARY KEY);\n";
        }

        return $sql;
    }

    /**
     * @param  array<string, string>  $files
     */
    private function archive(string $name, string $dump, array $files, int $olderBy = 7): void
    {
        $path = tempnam(sys_get_temp_dir(), 'zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->setPassword('sekret');

        foreach (['db-dumps/sqlite-database.sql' => $dump, ...$files] as $entry => $content) {
            $zip->addFromString($entry, $content);
            $zip->setEncryptionName($entry, ZipArchive::EM_AES_256);
        }

        $zip->close();

        $target = 'mellowaura-testing/'.$name;
        Storage::disk('local')->put($target, file_get_contents($path));
        touch(Storage::disk('local')->path($target), now()->subDays($olderBy)->timestamp);
        unlink($path);
    }
}
