<?php

namespace Tests\Feature\Content;

use App\Modules\Content\Mail\ContactMessageReceived;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_page_shows_the_channels_from_the_panel_and_never_the_studio_address(): void
    {
        $this->settings([
            'contact_phone' => '+48 600 100 200',
            'instagram_handle' => '@mellowaura.studio',
            'contact_email' => 'kasia@example.com',
            'studio_address' => 'ul. Tajna 7, Kraków',
            'company_name' => 'MellowAura Katarzyna Samborska',
            'company_nip' => '1111111111',
        ]);

        $response = $this->get('/kontakt')
            ->assertOk()
            ->assertSee('<title>Kontakt — pracownia MellowAura, Kraków</title>', false)
            ->assertSee('<meta name="description" content="Napisz na WhatsApp +48 600 100 200 albo przez formularz. Odpisuję zwykle tego samego dnia.">', false)
            ->assertSeeInOrder(['Napisz<br>do mnie', 'Odpisuję zwykle tego samego dnia, chyba że stoję przy piecu.'], false)
            ->assertSee('href="https://wa.me/48600100200"', false)
            ->assertSee('href="https://www.instagram.com/mellowaura.studio/"', false)
            ->assertSee('@mellowaura.studio')
            ->assertSee('href="mailto:kasia@example.com"', false)
            ->assertSeeInOrder(['pracownia', 'Kraków, okolice Błoń Krakowskich', 'Dokładny adres podaję przy zapisie na warsztat'])
            ->assertSeeInOrder(['W jakiej sprawie?', 'Zamówienie ze sklepu', 'Współpraca albo prasa'])
            ->assertSeeInOrder(['sprzedawca', 'MellowAura Katarzyna Samborska', 'NIP 1111111111'])
            ->assertDontSee('ul. Tajna 7');

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('content.contact'), '/').'"\s+aria-current="page"/', $response->getContent());
    }

    public function test_an_empty_channel_does_not_show(): void
    {
        $this->get('/kontakt')
            ->assertOk()
            ->assertSee('<meta name="description" content="Napisz do mnie przez formularz. Odpisuję zwykle tego samego dnia.">', false)
            ->assertDontSee('wa.me', false)
            ->assertDontSee('mailto:', false)
            ->assertDontSee('instagram.com', false)
            ->assertDontSee('sprzedawca');
    }

    public function test_a_message_goes_to_kasia_and_her_reply_goes_to_the_sender(): void
    {
        Mail::fake();
        $this->settings(['contact_email' => 'kasia@example.com']);

        $this->post('/kontakt', $this->form())->assertRedirect(route('content.contact').'#formularz');

        Mail::assertQueued(ContactMessageReceived::class, fn (ContactMessageReceived $mail) => $mail->hasTo('kasia@example.com')
            && $mail->hasReplyTo('ola@example.com', 'Ola')
            && $mail->hasSubject('Wiadomość ze strony: Warsztaty i terminy — Ola'));

        $this->get('/kontakt')
            ->assertSee('Wiadomość poszła. Odpisuję zwykle tego samego dnia.')
            ->assertDontSee('Czy w sobotę jest jeszcze miejsce?');
    }

    public function test_the_message_is_escaped_in_the_mail(): void
    {
        $mail = new ContactMessageReceived('Ola <b>', 'ola@example.com', null, "Pierwsza linia\n<script>alert(1)</script>");

        $mail->assertHasSubject('Wiadomość ze strony — Ola <b>');
        $mail->assertSeeInHtml('Ola &lt;b&gt;', false);
        $mail->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $mail->assertDontSeeInHtml('<script>alert(1)</script>', false);
        $mail->assertSeeInText("Pierwsza linia\n<script>alert(1)</script>", false);
    }

    public function test_the_form_says_what_is_missing_and_keeps_what_was_written(): void
    {
        Mail::fake();
        $this->settings(['contact_email' => 'kasia@example.com']);

        $this->followingRedirects()
            ->post('/kontakt', $this->form(['name' => '', 'email' => 'ola.example.com', 'topic' => 'Coś spoza listy']))
            ->assertSee('Wpisz imię — żebym wiedziała, jak się do Ciebie zwracać')
            ->assertSee('Adres e-mail bez małpy — sprawdź, czy nie uciekła')
            ->assertSee('Wybierz sprawę z listy')
            ->assertSee('value="ola.example.com"', false)
            ->assertSee('Czy w sobotę jest jeszcze miejsce?');

        $this->followingRedirects()
            ->post('/kontakt', $this->form(['message' => '']))
            ->assertSee('Napisz, w czym mogę pomóc')
            ->assertSee('aria-describedby="contact-message-error"', false);

        Mail::assertNothingOutgoing();
    }

    public function test_a_bot_that_fills_the_hidden_field_hears_the_same_and_nothing_is_sent(): void
    {
        Mail::fake();
        $this->settings(['contact_email' => 'kasia@example.com']);

        $this->followingRedirects()
            ->post('/kontakt', $this->form(['website' => 'https://spam.example']))
            ->assertSee('Wiadomość poszła. Odpisuję zwykle tego samego dnia.');

        Mail::assertNothingOutgoing();
    }

    public function test_without_an_e_mail_in_the_panel_the_message_waits_in_the_form(): void
    {
        Exceptions::fake();
        Mail::fake();
        $this->settings(['contact_phone' => '+48 600 100 200']);

        $this->followingRedirects()
            ->post('/kontakt', $this->form())
            ->assertSee('Coś się zacięło po mojej stronie i wiadomość nie wyszła.')
            ->assertSee('albo napisz na WhatsApp +48 600 100 200')
            ->assertSee('Czy w sobotę jest jeszcze miejsce?');

        Mail::assertNothingOutgoing();
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_after_five_messages_in_an_hour_the_next_one_waits(): void
    {
        Mail::fake();
        $this->settings(['contact_email' => 'kasia@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/kontakt', $this->form());
        }

        $this->followingRedirects()
            ->post('/kontakt', $this->form(['message' => 'Szósta wiadomość']))
            ->assertSee('Mam już od Ciebie kilka wiadomości i na wszystkie odpiszę.')
            ->assertSee('Szósta wiadomość');

        Mail::assertQueuedCount(5);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->where('key', $key)->update(['value' => json_encode($value)]);
        }
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Ola',
            'email' => 'ola@example.com',
            'topic' => 'Warsztaty i terminy',
            'message' => 'Czy w sobotę jest jeszcze miejsce?',
            ...$overrides,
        ];
    }
}
