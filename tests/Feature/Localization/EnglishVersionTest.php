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

    public function test_the_footer_speaks_the_language_of_the_page(): void
    {
        $footer = fn (string $html) => substr($html, strpos($html, '<footer'));

        $english = $footer($this->get('/en/shop')->getContent());
        $this->assertStringContainsString('>Shop everything</a>', $english);
        $this->assertStringContainsString('>Custom orders</a>', $english);
        $this->assertStringContainsString('href="'.url('/en/custom-orders').'"', $english);
        $this->assertStringContainsString('>Cookie settings</a>', $english);
        $this->assertStringContainsString('Studio visits by appointment', $english);

        // Studio services held in Polish, the Polish voucher category and the złoty payment line stay out.
        foreach (['Workshops &amp; prices', 'Kiln firing service', 'For cafés', 'Workshop vouchers', '/vouchery', 'BLIK', 'Pracownia na zapisy', 'Regulamin'] as $polishOnly) {
            $this->assertStringNotContainsString($polishOnly, $english);
        }

        $polish = $footer($this->get('/sklep')->getContent());
        $this->assertStringContainsString('>Wszystkie produkty</a>', $polish);
        $this->assertStringContainsString('>Warsztaty i cennik</a>', $polish);
        $this->assertStringContainsString('BLIK · Przelewy24 · karta', $polish);
    }

    public function test_the_home_page_speaks_english_and_leaves_out_what_is_polish_only(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('<title>MellowAura — handmade ceramics &amp; silk, Kraków</title>', false)
            ->assertSee('Clay and silk from <em class="text-brown italic">one pair of</em> hands.', false)
            ->assertSee('>Design your mug <span', false)
            ->assertSee('data-phases=\'[[0,"raw clay"]', false)
            ->assertSee('Let’s design it together')
            ->assertSee('>Basket</span>', false)
            // Złoty-only payment, services and workshops held in Polish, Kasia's Polish panel texts.
            ->assertDontSee('płatność w 10 sekund')
            ->assertDontSee('pay in 10 seconds')
            ->assertDontSee('Send me')
            ->assertDontSee('Sink your hands')
            ->assertDontSee('Ceramics for cafés and restaurants')
            ->assertDontSee('Hi, I’m Kasia');

        $this->get('/')
            ->assertOk()
            ->assertSee('Glina i jedwab z <em class="text-brown italic">jednej pary</em> rąk.', false)
            ->assertSee('płatność w 10 sekund')
            ->assertSee('>Koszyk</span>', false);
    }

    public function test_the_gift_finder_is_polish_only_and_english_gifts_lead_to_the_gift_sets(): void
    {
        $this->get('/en/gifts')->assertNotFound();

        $this->get('/en/shop')
            ->assertSee('href="'.url('/en/gift-sets').'"', false)
            ->assertSee('>Gifts</a>', false)
            ->assertDontSee('Find a gift');

        $this->get('/prezenty')
            ->assertOk()
            ->assertSee('href="'.url('/en/gift-sets').'?unavailable=1" hreflang="en"', false);

        $this->get('/sklep')->assertSee('href="'.url('/prezenty').'"', false);
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
