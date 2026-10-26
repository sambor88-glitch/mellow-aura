<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Content\Mail\ContactMessageReceived;
use App\Modules\Monitoring\Mail\Alert;
use App\Modules\Monitoring\Models\Heartbeat;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class AlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['monitoring.alert_email' => 'opiekun@example.com']);
    }

    public function test_an_error_the_site_reports_is_mailed_once_an_hour(): void
    {
        Mail::fake();
        // Built in a closure, so the trace passes through a file of the shop, as an error from app/ would.
        $error = fn () => new RuntimeException('Nie ma tabeli orders');

        foreach (range(1, 3) as $pageView) {
            report($error());
        }

        Mail::assertSent(Alert::class, 1);
        Mail::assertSent(Alert::class, fn (Alert $mail) => $mail->hasTo('opiekun@example.com')
            && str_starts_with($mail->summary, 'Błąd RuntimeException w tests/Feature/Monitoring/AlertsTest.php:')
            && str_contains($mail->details, 'Komunikat: Nie ma tabeli orders')
            && str_contains($mail->details, 'Pliki sklepu w śladzie błędu:'));

        $this->travel(61)->minutes();
        report($error());

        Mail::assertSent(Alert::class, 2);
    }

    public function test_without_an_address_the_alert_only_reaches_the_log(): void
    {
        config(['monitoring.alert_email' => null]);
        Mail::fake();

        report(new RuntimeException('Nie ma tabeli orders'));

        Mail::assertNothingOutgoing();
    }

    public function test_an_alert_that_cannot_be_sent_never_breaks_the_page(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Serwer poczty nie odpowiada'));

        report(new RuntimeException('Nie ma tabeli orders'));

        $this->get('/nie-ma-takiej-strony')->assertNotFound();
    }

    public function test_every_minute_the_scheduler_leaves_a_sign_of_life_and_checks_the_worker(): void
    {
        config(['queue.default' => 'database']);
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'monitoring:check'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->evenInMaintenanceMode);

        Mail::fake();
        Heartbeat::record(Heartbeat::QUEUE);
        $this->travel(5)->minutes();

        $this->artisan('monitoring:check')->assertSuccessful();

        $this->assertSame(0, Heartbeat::silentFor(Heartbeat::SCHEDULER));
        Mail::assertNothingOutgoing();
    }

    public function test_a_worker_silent_for_ten_minutes_raises_an_alert_with_the_waiting_mails(): void
    {
        config(['queue.default' => 'database']);
        Heartbeat::record(Heartbeat::QUEUE);
        $this->queueMail();
        $this->travel(11)->minutes();
        Mail::fake();

        $this->artisan('monitoring:check')->assertSuccessful();
        $this->artisan('monitoring:check')->assertSuccessful();

        Mail::assertSent(Alert::class, 1);
        Mail::assertSent(Alert::class, fn (Alert $mail) => $mail->summary === 'Kolejka nie działa od 11 min'
            && str_contains($mail->details, 'Zadań w kolejce: 1'));
    }

    public function test_a_job_waiting_ten_minutes_raises_an_alert_even_when_the_worker_never_started(): void
    {
        config(['queue.default' => 'database']);
        $this->queueMail();
        $this->travel(12)->minutes();
        Mail::fake();

        $this->artisan('monitoring:check')->assertSuccessful();

        Mail::assertSent(Alert::class, fn (Alert $mail) => $mail->summary === 'Zadanie czeka w kolejce od 12 min');
    }

    public function test_a_queue_without_a_worker_is_not_watched(): void
    {
        config(['queue.default' => 'sync']);
        Heartbeat::record(Heartbeat::QUEUE);
        $this->travel(30)->minutes();
        Mail::fake();

        $this->artisan('monitoring:check')->assertSuccessful();

        Mail::assertNothingOutgoing();
        $this->assertSame(0, Heartbeat::silentFor(Heartbeat::SCHEDULER));
    }

    public function test_the_worker_leaves_its_sign_of_life_and_raises_an_alert_when_the_scheduler_went_silent(): void
    {
        Heartbeat::record(Heartbeat::SCHEDULER);
        $this->travel(15)->minutes();
        Mail::fake();

        $result = event(new Looping('database', 'default'));

        // A Looping listener that returned false would pause the worker.
        $this->assertNotContains(false, $result);
        $this->assertSame(0, Heartbeat::silentFor(Heartbeat::QUEUE));
        Mail::assertSent(Alert::class, fn (Alert $mail) => $mail->summary === 'Harmonogram nie działa od 15 min');
    }

    public function test_a_scheduler_that_never_ran_here_raises_nothing(): void
    {
        Mail::fake();

        event(new Looping('database', 'default'));

        Mail::assertNothingOutgoing();
    }

    public function test_failed_jobs_are_pruned_after_thirty_days(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'queue:prune-failed'));

        $this->assertNotNull($event);
        $this->assertStringContainsString('--hours=720', (string) $event->command);
        $this->assertSame('0 0 * * *', $event->expression);
    }

    private function queueMail(): void
    {
        Mail::to('kasia@example.com')->send(new ContactMessageReceived('Anna Nowak', 'ania@example.com', null, 'Dzień dobry'));
    }
}
