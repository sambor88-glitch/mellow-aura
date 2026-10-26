<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Mail\PanelPasswordLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PanelPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_forgotten_password_gets_a_link_and_the_answer_never_tells_who_has_an_account(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'kasia@example.com']);
        $answer = 'Jeśli to adres konta w panelu, za chwilę dostaniesz maila z linkiem do nowego hasła.';

        $this->get('/panel/logowanie')->assertSee('href="'.route('admin.password.request').'"', false)->assertSee('Nie pamiętasz hasła?');
        $this->get('/panel/haslo')->assertOk()->assertSee('Wyślij link do nowego hasła')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->from('/panel/haslo')->post('/panel/haslo', ['email' => 'Kasia@example.com'])->assertSessionHas('password_status', $answer);
        $this->from('/panel/haslo')->post('/panel/haslo', ['email' => 'nieznana@example.com'])->assertSessionHas('password_status', $answer);

        Mail::assertQueuedCount(1);
        Mail::assertQueued(PanelPasswordLink::class, function (PanelPasswordLink $mail) {
            $mail->assertHasSubject('Nowe hasło do panelu MellowAury');
            $mail->assertSeeInHtml('Ktoś — pewnie Ty — poprosił o nowe hasło');
            $mail->assertSeeInHtml(route('admin.password.reset', ['token' => $mail->token, 'email' => 'kasia@example.com']), false);
            $mail->assertSeeInText('Link działa 60 minut.');

            return $mail->hasTo('kasia@example.com') && ! $mail->invitation;
        });
    }

    public function test_the_link_sets_a_new_password_once_and_the_old_one_stops_working(): void
    {
        $owner = User::factory()->create(['email' => 'kasia@example.com', 'password' => 'stare-haslo-do-panelu']);
        $token = Password::broker()->createToken($owner);

        $this->get('/panel/haslo/'.$token.'?email=kasia@example.com')
            ->assertOk()
            ->assertSee('value="kasia@example.com"', false)
            ->assertSee('name="token" value="'.$token.'"', false);

        $this->from('/panel/haslo/'.$token)
            ->post('/panel/haslo/nowe', ['token' => $token, 'email' => 'kasia@example.com', 'password' => 'nowe-dlugie-haslo', 'password_confirmation' => 'inne-haslo-zupelnie'])
            ->assertSessionHasErrors(['password' => 'Hasła się różnią — wpisz to samo dwa razy']);

        $this->post('/panel/haslo/nowe', ['token' => $token, 'email' => 'kasia@example.com', 'password' => 'nowe-dlugie-haslo', 'password_confirmation' => 'nowe-dlugie-haslo'])
            ->assertRedirect('/panel/logowanie')
            ->assertSessionHas('login_status', 'Nowe hasło zapisane — zaloguj się nim.');
        $this->assertTrue(Hash::check('nowe-dlugie-haslo', $owner->fresh()->password));

        // The same link doesn't work twice.
        $this->from('/panel/haslo/'.$token)
            ->post('/panel/haslo/nowe', ['token' => $token, 'email' => 'kasia@example.com', 'password' => 'jeszcze-inne-haslo', 'password_confirmation' => 'jeszcze-inne-haslo'])
            ->assertSessionHasErrors(['email' => 'Ten link już nie działa — poproś o nowy na stronie logowania']);

        $this->post('/panel/logowanie', ['email' => 'kasia@example.com', 'password' => 'stare-haslo-do-panelu'])->assertSessionHasErrors('email');
        $this->post('/panel/logowanie', ['email' => 'kasia@example.com', 'password' => 'nowe-dlugie-haslo'])->assertRedirect('/panel');
    }
}
