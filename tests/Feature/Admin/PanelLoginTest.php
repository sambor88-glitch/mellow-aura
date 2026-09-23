<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanelLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_panel_is_behind_a_login_and_kept_out_of_search(): void
    {
        $this->get('/panel')->assertRedirect('/panel/logowanie');

        $this->get('/panel/logowanie')
            ->assertOk()
            ->assertSee('Zaloguj się')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('Wyloguj');
    }

    public function test_the_owner_signs_in_sees_the_sections_and_signs_out(): void
    {
        $owner = User::factory()->create(['email' => 'kasia@example.com', 'password' => 'bardzo-dlugie-haslo']);

        $this->post('/panel/logowanie', ['email' => 'Kasia@example.com', 'password' => 'bardzo-dlugie-haslo'])
            ->assertRedirect('/panel');
        $this->assertAuthenticatedAs($owner);

        $this->get('/panel')
            ->assertOk()
            ->assertSee('Mój panel')
            ->assertSee('href="'.route('admin.orders.index').'"', false)
            ->assertSee('Wyloguj');

        $this->get('/panel/logowanie')->assertRedirect('/panel');

        $this->post('/panel/wyloguj')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_a_wrong_password_says_what_to_do_and_repeated_tries_have_to_wait(): void
    {
        User::factory()->create(['email' => 'kasia@example.com', 'password' => 'bardzo-dlugie-haslo']);

        $this->from('/panel/logowanie')
            ->post('/panel/logowanie', ['email' => 'kasia@example.com', 'password' => 'zle-haslo'])
            ->assertRedirect('/panel/logowanie')
            ->assertSessionHasErrors(['email' => 'Ten e-mail albo hasło się nie zgadza — sprawdź i spróbuj jeszcze raz']);

        foreach (range(1, 4) as $attempt) {
            $this->post('/panel/logowanie', ['email' => 'kasia@example.com', 'password' => 'zle-haslo']);
        }

        $this->post('/panel/logowanie', ['email' => 'kasia@example.com', 'password' => 'bardzo-dlugie-haslo'])
            ->assertSessionHasErrors(['email' => 'Za dużo prób logowania. Odczekaj minutę i spróbuj jeszcze raz']);
        $this->assertGuest();
    }

    public function test_the_command_creates_the_account_and_replaces_a_forgotten_password(): void
    {
        $this->artisan('admin:user', ['email' => 'Kasia@Example.com', '--name' => 'Kasia'])
            ->expectsQuestion('Password (at least 12 characters)', 'bardzo-dlugie-haslo')
            ->assertSuccessful();

        $owner = User::sole();
        $this->assertSame(['kasia@example.com', 'Kasia'], [$owner->email, $owner->name]);
        $this->assertTrue(Hash::check('bardzo-dlugie-haslo', $owner->password));

        $this->artisan('admin:user', ['email' => 'kasia@example.com'])
            ->expectsQuestion('Password (at least 12 characters)', 'krotkie')
            ->assertFailed();

        $this->artisan('admin:user', ['email' => 'kasia@example.com'])
            ->expectsQuestion('Password (at least 12 characters)', 'zupelnie-nowe-haslo')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('zupelnie-nowe-haslo', $owner->fresh()->password));
        $this->assertSame('Kasia', $owner->fresh()->name);
        $this->assertSame(1, User::count());
    }
}
