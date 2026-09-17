<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Checkout\Mail\ComplaintAnswered;
use App\Modules\Checkout\Mail\NewOrderReceived;
use App\Modules\Checkout\Mail\NewWithdrawalReceived;
use App\Modules\Checkout\Mail\OrderAwaitingPayment;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Mail\OrderUnavailable;
use App\Modules\Checkout\Mail\WithdrawalConfirmed;
use App\Modules\Content\Mail\ContactMessageReceived;
use App\Modules\Gifts\Mail\VouchersIssued;
use App\Modules\Monitoring\Mail\Alert;
use App\Modules\Shared\Mail\QueuedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use ReflectionProperty;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class QueuedMailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);

        // queue:work registers its failed-job logging once per PHP process; each test has a new application.
        (new ReflectionProperty(WorkCommand::class, 'hasRegisteredListeners'))->setValue(null, false);
    }

    public function test_mails_to_customers_and_to_kasia_go_through_the_queue_and_the_complaint_answer_goes_at_once(): void
    {
        foreach ([OrderAwaitingPayment::class, OrderConfirmed::class, OrderUnavailable::class, NewOrderReceived::class, WithdrawalConfirmed::class, NewWithdrawalReceived::class, VouchersIssued::class, ContactMessageReceived::class] as $mail) {
            $this->assertTrue(is_subclass_of($mail, QueuedMail::class), $mail);
        }

        $this->assertFalse(is_subclass_of(ComplaintAnswered::class, ShouldQueue::class));
        $this->assertFalse(is_subclass_of(Alert::class, ShouldQueue::class));
    }

    public function test_a_queued_mail_says_what_it_is_and_to_whom_and_may_be_tried_six_times(): void
    {
        $this->sendContactMessage();

        $payload = json_decode(DB::table('jobs')->value('payload'), true);

        $this->assertSame(['description' => 'Wiadomość z formularza kontaktowego — Anna Nowak', 'to' => ['kasia@example.com']], $payload['mail']);
        $this->assertSame(6, $payload['maxTries']);
        $this->assertSame('60,300,900,1800,3600', $payload['backoff']);
    }

    public function test_the_worker_sends_the_mail_from_the_queue(): void
    {
        $this->sendContactMessage();

        $this->work();

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Wiadomość ze strony — Anna Nowak', $messages[0]->getOriginalMessage()->getSubject());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_a_mail_that_fails_is_tried_again_for_two_hours_and_then_waits_on_the_list_with_an_alert(): void
    {
        config(['monitoring.alert_email' => 'opiekun@example.com', 'mail.mailers.broken' => ['transport' => 'broken']]);
        Mail::extend('broken', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Serwer poczty nie odpowiada');
            }

            public function __toString(): string
            {
                return 'broken://';
            }
        });
        Mail::mailer('broken')->to('kasia@example.com')->send(new ContactMessageReceived('Anna Nowak', 'ania@example.com', null, 'Dzień dobry'));

        foreach (range(1, 5) as $attempt) {
            $this->work();
            $this->assertSame(1, DB::table('jobs')->count(), 'attempt '.$attempt);
            $this->assertSame(0, DB::table('failed_jobs')->count(), 'attempt '.$attempt);
            $this->travel(61)->minutes();
        }

        $this->work();

        $this->assertSame(0, DB::table('jobs')->count());
        $failed = DB::table('failed_jobs')->sole();
        $this->assertSame('Wiadomość z formularza kontaktowego — Anna Nowak', json_decode($failed->payload, true)['mail']['description']);

        // The alert goes out at once through the site's own mailer, not through the broken one.
        $alerts = collect(Mail::mailer('array')->getSymfonyTransport()->messages())
            ->map(fn (SentMessage $message) => $message->getOriginalMessage())
            ->filter(fn ($message) => str_contains($message->getSubject(), 'Mail nie wyszedł'));
        $this->assertCount(1, $alerts);
        $this->assertSame('opiekun@example.com', $alerts->first()->getTo()[0]->getAddress());
        $this->assertStringContainsString('Mail nie wyszedł: Wiadomość z formularza kontaktowego — Anna Nowak', $alerts->first()->getSubject());
        $this->assertStringContainsString('Do: kasia@example.com', $alerts->first()->getTextBody());
        $this->assertStringContainsString('Powód: Serwer poczty nie odpowiada', $alerts->first()->getTextBody());
        $this->assertStringContainsString('/panel/niewyslane-maile', $alerts->first()->getTextBody());
    }

    private function sendContactMessage(): void
    {
        Mail::to(new Address('kasia@example.com'))->send(new ContactMessageReceived('Anna Nowak', 'ania@example.com', null, 'Dzień dobry'));
    }

    private function work(): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]);
    }
}
