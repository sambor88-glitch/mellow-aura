<?php

namespace Tests\Feature\Monitoring;

use App\Models\User;
use App\Modules\Content\Mail\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AdminFailedMailsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->owner = User::factory()->create();
        config(['queue.default' => 'database']);
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $id = $this->failedMail('Anna Nowak');

        $this->get('/panel/niewyslane-maile')->assertRedirect('/panel/logowanie');
        $this->post('/panel/niewyslane-maile/'.$id.'/wyslij')->assertRedirect('/panel/logowanie');
        $this->delete('/panel/niewyslane-maile/'.$id)->assertRedirect('/panel/logowanie');
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    public function test_with_nothing_failed_the_list_says_every_mail_went_out(): void
    {
        // A job that is not a mail is only for the person who looks after the shop.
        app(FailedJobProviderInterface::class)->log('database', 'default', json_encode(['uuid' => (string) Str::uuid(), 'displayName' => 'App\\Jobs\\CreateLabel']), new RuntimeException('InPost nie odpowiada'));

        $this->actingAs($this->owner)->get('/panel/niewyslane-maile')
            ->assertOk()
            ->assertSee('Wszystkie maile wyszły')
            ->assertDontSee('InPost nie odpowiada');
    }

    public function test_a_mail_that_did_not_go_out_shows_what_it_is_to_whom_and_why(): void
    {
        $this->failedMail('Anna Nowak');

        $this->actingAs($this->owner)->get('/panel/niewyslane-maile')
            ->assertOk()
            ->assertSeeInOrder(['Wiadomość z formularza kontaktowego — Anna Nowak', 'Do: kasia@example.com', 'Szczegóły techniczne', 'Serwer poczty nie odpowiada', 'Wyślij jeszcze raz', 'Usuń z listy'])
            ->assertDontSee('Wyślij wszystkie jeszcze raz');
    }

    public function test_sending_again_puts_the_same_mail_back_in_the_queue(): void
    {
        $id = $this->failedMail('Anna Nowak');

        $this->actingAs($this->owner)->post('/panel/niewyslane-maile/'.$id.'/wyslij')
            ->assertRedirect('/panel/niewyslane-maile')
            ->assertSessionHas('panel_status', 'Wysyłam jeszcze raz. Jeśli się nie uda, wróci na listę.');

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $job = DB::table('jobs')->sole();
        $this->assertSame('Wiadomość z formularza kontaktowego — Anna Nowak', json_decode($job->payload, true)['mail']['description']);
        $this->assertSame(0, (int) $job->attempts);
    }

    public function test_several_mails_go_back_to_the_queue_at_once(): void
    {
        $this->failedMail('Anna Nowak');
        $this->failedMail('Ewa Kowalska');

        $this->actingAs($this->owner)->get('/panel/niewyslane-maile')->assertSee('Wyślij wszystkie jeszcze raz');
        $this->actingAs($this->owner)->post('/panel/niewyslane-maile/wyslij-wszystkie')->assertRedirect('/panel/niewyslane-maile');

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(2, DB::table('jobs')->count());
    }

    public function test_a_mail_can_be_taken_off_the_list(): void
    {
        $id = $this->failedMail('Anna Nowak');

        $this->actingAs($this->owner)->delete('/panel/niewyslane-maile/'.$id)
            ->assertRedirect('/panel/niewyslane-maile')
            ->assertSessionHas('panel_status', 'Usunięte z listy.');

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_a_second_click_on_a_mail_already_sent_again_only_says_it_is_gone(): void
    {
        $id = $this->failedMail('Anna Nowak');
        $this->actingAs($this->owner)->post('/panel/niewyslane-maile/'.$id.'/wyslij');

        $this->actingAs($this->owner)->post('/panel/niewyslane-maile/'.$id.'/wyslij')
            ->assertRedirect('/panel/niewyslane-maile')
            ->assertSessionHas('panel_status', 'Tego maila nie ma już na liście.');
        $this->actingAs($this->owner)->delete('/panel/niewyslane-maile/'.$id)
            ->assertSessionHas('panel_status', 'Tego maila nie ma już na liście.');

        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_the_dashboard_says_how_many_mails_wait_and_the_menu_leads_to_them(): void
    {
        // The menu card describes the list in similar words, so the notice is recognised by its link text.
        $this->actingAs($this->owner)->get('/panel')->assertOk()->assertDontSee('Zobacz, które');

        $this->failedMail('Anna Nowak');
        $this->actingAs($this->owner)->get('/panel')
            ->assertSee('1 mail nie wyszedł mimo kilku prób.')
            ->assertSee('href="'.route('admin.failed-mails.index').'"', false)
            ->assertSee('Niewysłane maile');

        $this->failedMail('Ewa Kowalska');
        $this->actingAs($this->owner)->get('/panel')->assertSee('2 maile nie wyszły mimo kilku prób.');

        foreach (['Ola', 'Iza', 'Zosia'] as $name) {
            $this->failedMail($name);
        }
        $this->actingAs($this->owner)->get('/panel')->assertSee('5 maili nie wyszło mimo kilku prób.');
    }

    /**
     * A real queued mail moved to the failed jobs, as the worker leaves it after the last attempt.
     */
    private function failedMail(string $sender): string
    {
        Mail::to('kasia@example.com')->send(new ContactMessageReceived($sender, 'ania@example.com', null, 'Dzień dobry'));

        $job = DB::table('jobs')->latest('id')->first();
        DB::table('jobs')->where('id', $job->id)->delete();

        return app(FailedJobProviderInterface::class)->log('database', 'default', $job->payload, new RuntimeException('Serwer poczty nie odpowiada'));
    }
}
