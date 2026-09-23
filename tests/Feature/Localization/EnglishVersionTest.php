<?php

namespace Tests\Feature\Localization;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnglishVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_an_english_twin_is_the_same_page_under_an_english_address(): void
    {
        $this->get('/en/shop')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('<meta property="og:locale" content="en_GB">', false);

        $this->get('/sklep')
            ->assertOk()
            ->assertSee('<html lang="pl">', false);
    }

    public function test_links_on_an_english_page_lead_to_english_addresses(): void
    {
        $this->get('/en/shop')
            ->assertSee('href="'.url('/en/about').'"', false)
            ->assertSee('href="'.url('/en/contact').'"', false)
            ->assertSee('href="'.url('/en').'"', false)
            ->assertSee('>About</a>', false);
    }

    public function test_polish_only_pages_have_no_english_address_and_stay_out_of_the_english_menu(): void
    {
        $this->get('/en/workshops')->assertNotFound();
        $this->get('/en/warsztaty-ceramiczne-krakow')->assertNotFound();

        $this->get('/en/shop')
            ->assertDontSee('>Workshops</a>', false)
            ->assertDontSee('>Warsztaty</a>', false);

        $this->get('/sklep')->assertSee('>Warsztaty</a>', false);
    }

    public function test_a_page_with_a_twin_points_search_engines_to_both(): void
    {
        $this->get('/sklep')
            ->assertSee('<link rel="alternate" hreflang="pl" href="'.url('/sklep').'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/shop').'">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/sklep').'">', false)
            ->assertSee('<meta property="og:locale:alternate" content="en_GB">', false);

        $this->get('/en/shop')
            ->assertSee('<link rel="alternate" hreflang="pl" href="'.url('/sklep').'">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/sklep').'">', false);
    }

    public function test_a_polish_only_page_has_nothing_to_point_to(): void
    {
        // The language switch still carries hreflang="en" on its link; the page itself declares no alternate.
        $this->get('/warsztaty-ceramiczne-krakow')
            ->assertOk()
            ->assertDontSee('<link rel="alternate" hreflang=', false)
            ->assertDontSee('og:locale:alternate', false);
    }

    public function test_the_switch_leads_to_the_same_page_in_the_other_language(): void
    {
        $this->get('/sklep')->assertSee('href="'.url('/en/shop').'" hreflang="en" lang="en"', false);
        $this->get('/en/shop')->assertSee('href="'.url('/sklep').'" hreflang="pl" lang="pl"', false);
    }

    public function test_from_a_polish_only_page_the_switch_leads_to_the_nearest_page_and_says_why(): void
    {
        $this->get('/warsztaty-ceramiczne-krakow')
            ->assertSee('href="'.url('/en/about').'?unavailable=1" hreflang="en"', false);

        $this->get('/wypal-ceramiki-krakow')
            ->assertSee('href="'.url('/en/studio').'?unavailable=1" hreflang="en"', false);

        $this->get('/en/about?unavailable=1')
            ->assertOk()
            ->assertSee('The page you came from is in Polish only');

        $this->get('/en/about')->assertDontSee('The page you came from is in Polish only');
    }

    public function test_the_browser_language_only_offers_the_other_version(): void
    {
        $this->get('/sklep', ['Accept-Language' => 'en-GB,en;q=0.9'])
            ->assertOk()
            ->assertSee('This page is also available in English.')
            ->assertSee('Switch to English');

        $this->get('/sklep', ['Accept-Language' => 'pl-PL,pl;q=0.9,en;q=0.8'])
            ->assertDontSee('This page is also available in English.');

        // Nothing to offer where there is no English page.
        $this->get('/warsztaty-ceramiczne-krakow', ['Accept-Language' => 'en-GB'])
            ->assertDontSee('This page is also available in English.');
    }

    public function test_redirects_after_a_form_stay_in_the_language_of_the_form(): void
    {
        // No page to go back to, so the controller falls back to route('home') — the English one here.
        $this->post('/en/cookie-settings', ['analytics' => '0'])->assertRedirect(url('/en'));
        $this->post('/ustawienia-cookies', ['analytics' => '0'])->assertRedirect(url('/'));
    }

    public function test_with_english_switched_off_nothing_points_to_it(): void
    {
        config(['localization.enabled' => ['pl']]);

        $this->get('/sklep')
            ->assertOk()
            ->assertDontSee('<link rel="alternate" hreflang=', false)
            ->assertDontSee('hreflang="en" lang="en"', false)
            ->assertDontSee('og:locale:alternate', false);

        $this->get('/sklep', ['Accept-Language' => 'en'])
            ->assertDontSee('This page is also available in English.');
    }
}
