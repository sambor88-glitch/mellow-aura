<?php

namespace Tests\Feature\Consent;

use App\Models\User;
use App\Modules\Consent\Support\Consent;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    private const GTAG = 'https://www.googletagmanager.com/gtag/js';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_without_analytics_in_the_panel_there_is_no_banner_and_no_google_script(): void
    {
        $this->get('/kontakt')
            ->assertOk()
            ->assertDontSee('consentBanner', false)
            ->assertDontSee(self::GTAG, false)
            ->assertSee('href="'.route('consent.edit').'" data-consent-open', false);

        $this->get('/ustawienia-cookies')
            ->assertOk()
            ->assertSee('Innych plików teraz nie używam, więc nie ma tu nic do wybrania.')
            ->assertDontSee('Zapisz wybór');
    }

    public function test_the_banner_asks_first_and_google_waits_for_a_yes(): void
    {
        $this->analytics('G-AB12CD34EF');

        $this->get('/sklep')
            ->assertOk()
            ->assertSee('x-data="consentBanner(', false)
            ->assertDontSee('style="display: none"', false)
            ->assertSeeInOrder(['Koszyk i formularze działają na niezbędnych plikach cookies.', 'Zgadzam się na statystyki', 'Tylko niezbędne'])
            ->assertDontSee(self::GTAG, false);
    }

    public function test_a_yes_is_remembered_for_a_year_and_loads_analytics_in_basic_consent_mode(): void
    {
        $this->analytics('G-AB12CD34EF');
        $this->travelTo(Carbon::parse('2026-09-16 20:15'));

        $response = $this->from('/kontakt')->post('/ustawienia-cookies', ['analytics' => '1'])
            ->assertRedirect('/kontakt')
            ->assertCookie(Consent::COOKIE, '1.analytics.'.now()->timestamp);

        $cookie = collect($response->headers->getCookies())->firstWhere(fn ($cookie) => $cookie->getName() === Consent::COOKIE);
        $this->assertSame(now()->addDays(360)->timestamp, $cookie->getExpiresTime());

        $this->withCookie(Consent::COOKIE, '1.analytics.'.now()->timestamp)
            ->get('/kontakt')
            ->assertSeeInOrder([
                "gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied' });",
                "gtag('consent', 'update', { analytics_storage: 'granted' });",
                "gtag('config', 'G-AB12CD34EF');",
                self::GTAG.'?id=G-AB12CD34EF',
            ], false)
            ->assertSee('style="display: none"', false);
    }

    public function test_a_no_keeps_google_out_and_clears_its_cookies(): void
    {
        $this->analytics('G-AB12CD34EF');

        $response = $this->withCookies(['_ga' => 'GA1.1.123', '_ga_AB12CD34EF' => 'GS1.1.456', 'mellowaura-session' => 'x'])
            ->withHeader('Accept', 'application/json')
            ->post('/ustawienia-cookies', ['analytics' => '0'])
            ->assertOk()
            ->assertExactJson(['analytics' => false]);

        $cleared = collect($response->headers->getCookies())
            ->filter(fn ($cookie) => $cookie->getExpiresTime() < time())
            ->map(fn ($cookie) => $cookie->getName().'@'.($cookie->getDomain() ?? 'host'))
            ->sort()->values()->all();
        $this->assertSame(['_ga@.localhost', '_ga@host', '_ga_AB12CD34EF@.localhost', '_ga_AB12CD34EF@host'], $cleared);

        $this->withCookie(Consent::COOKIE, '1.necessary.'.now()->timestamp)
            ->get('/kontakt')
            ->assertDontSee(self::GTAG, false)
            ->assertSee('style="display: none"', false);
    }

    public function test_an_old_or_broken_choice_asks_again(): void
    {
        $this->analytics('G-AB12CD34EF');

        foreach (['0.analytics.1768590000', 'tak', '1.analytics.jutro'] as $value) {
            $this->withCookie(Consent::COOKIE, $value)
                ->get('/kontakt')
                ->assertDontSee(self::GTAG, false)
                ->assertDontSee('style="display: none"', false);
        }
    }

    public function test_the_settings_page_shows_the_choice_and_saves_a_change_without_javascript(): void
    {
        $this->analytics('G-AB12CD34EF');
        $this->travelTo(Carbon::parse('2026-09-16 20:15'));

        $this->withCookie(Consent::COOKIE, '1.analytics.'.now()->timestamp)
            ->get('/ustawienia-cookies')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('value="1" checked', false)
            ->assertSee('Twój wybór z 16 września 2026.');

        $this->post('/ustawienia-cookies', [])->assertSessionHasErrors('analytics');

        // The browser sends back the cookie the post has just set.
        $this->withCookie(Consent::COOKIE, '1.necessary.'.now()->timestamp)
            ->from('/ustawienia-cookies')
            ->followingRedirects()
            ->post('/ustawienia-cookies', ['analytics' => '0'])
            ->assertSee('Zapisane. Zostają tylko niezbędne pliki.')
            ->assertSee('value="0" checked', false);
    }

    public function test_the_measurement_id_is_set_in_the_panel_and_filled_into_the_privacy_policy(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->put('/panel/ustawienia/statystyki', ['google_analytics_id' => ' g-ab12cd34ef '])
            ->assertRedirect('/panel/ustawienia#statystyki')
            ->assertSessionHas('panel_status', 'Zapisane. Statystyki ruszą po zgodzie w okienku.');
        $this->assertSame('G-AB12CD34EF', Setting::find('google_analytics_id')->value);
        $this->get('/panel/ustawienia')->assertSeeInOrder(['Statystyki — Google Analytics', 'value="G-AB12CD34EF"'], false);

        $this->from('/panel/ustawienia')
            ->followingRedirects()
            ->put('/panel/ustawienia/statystyki', ['google_analytics_id' => 'UA-12345-1'])
            ->assertSee('Identyfikator zaczyna się od G-, np. G-AB12CD34EF — skopiuj go z Google Analytics');
        $this->assertSame('G-AB12CD34EF', Setting::find('google_analytics_id')->value);

        $this->get('/polityka-prywatnosci')
            ->assertSee('<td>_ga, _ga_AB12CD34EF</td>', false)
            ->assertSee('<td>mellowaura-consent</td><td>sklep</td>', false);

        $this->put('/panel/ustawienia/statystyki', ['google_analytics_id' => ''])
            ->assertSessionHas('panel_status', 'Statystyki i okienko zgód wyłączone');
        $this->assertNull(Setting::find('google_analytics_id')->value);
    }

    private function analytics(string $id): void
    {
        Setting::query()->where('key', 'google_analytics_id')->update(['value' => json_encode($id)]);
    }
}
