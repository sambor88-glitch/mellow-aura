<?php

namespace Tests\Feature\Content;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudioPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_studio_page_shows_the_texts_facts_and_photos_from_the_panel_without_the_address(): void
    {
        $this->settings(['studio_address' => 'ul. Tajna 7, Kraków']);

        $this->get('/pracownia')
            ->assertOk()
            ->assertSee('<title>Pracownia w Krakowie — ciepło, empatia, cierpliwość</title>', false)
            ->assertSee('<meta name="description" content="Kameralna domowa pracownia w Krakowie: do 6 osób, od 5 lat, dwa wypały w cenie, wysyłka prac.">', false)
            ->assertSee('<link rel="canonical" href="'.route('content.studio').'">', false)
            ->assertSeeInOrder(['pracownia &middot; kraków', 'Kameralna pracownia. Ciepło, empatia, cierpliwość.'], false)
            ->assertSee('from-scrim/72', false)
            ->assertSeeText('poczuj spokój, radość i twórczą swobodę.')
            ->assertSeeText('to domowa pracownia, więc wpuszczam po jednej grupie naraz')
            ->assertDontSeeText('home studio')
            ->assertSeeInOrder([
                'Warsztaty, które uczą nie tylko ceramiki',
                'Przy jednym stole siada najwyżej sześć osób',
                'Dokładny adres podaję po zapisaniu się',
            ])
            ->assertSeeInOrder(['Dobrze wiedzieć', 'Do 6 osób', 'Przy jednym stole, bez tłoku.', 'Wysyłka prac', 'Jeśli nie możesz przyjechać — wyślę.'])
            ->assertSeeInOrder(['alt="Kubek z wbijanym tekstem"', 'alt="Filiżanka na talerzyku z malowaną lawendą"'], false)
            ->assertDontSee('ul. Tajna 7');
    }

    public function test_the_main_photo_keeps_its_place_and_offers_smaller_copies_to_a_phone(): void
    {
        $page = $this->get('/pracownia')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<img src="[^"]*" alt="" fetchpriority="high"\s+srcset="[^"]+ 640w, [^"]+" sizes="100vw"\s+width="\d+" height="\d+"/', $page);
    }

    public function test_a_missing_photo_or_an_empty_fact_is_left_out(): void
    {
        $this->settings([
            'studio_facts' => [['title' => 'Do 4 osób', 'text' => ''], ['title' => '', 'text' => 'Bez tytułu'], 'nie fakt'],
            'page_photos' => [
                'studio-hero' => ['path' => 'zdjecia/nie-ma-takiego.webp'],
                'studio-gallery-1' => ['path' => 'zdjecia/nie-ma-takiego.webp', 'alt' => 'Zgubione zdjęcie'],
                'studio-gallery-2' => ['path' => 'zdjecia/misa-laguna.webp', 'alt' => 'Misa w kolorze laguny'],
            ],
        ]);

        $this->get('/pracownia')
            ->assertOk()
            ->assertDontSee('from-scrim/72', false)
            ->assertSee('Kameralna pracownia. Ciepło, empatia, cierpliwość.')
            ->assertSee('<dt class="text-ink">Do 4 osób</dt>', false)
            ->assertDontSee('Bez tytułu')
            ->assertDontSee('nie fakt')
            ->assertDontSee('Zgubione zdjęcie')
            ->assertSee('alt="Misa w kolorze laguny"', false);
    }

    public function test_the_facts_card_takes_its_heading_from_the_panel(): void
    {
        $this->settings(['studio_facts_heading' => 'Zanim przyjdziesz <b>']);

        $this->get('/pracownia')
            ->assertOk()
            ->assertSeeInOrder(['<h2 class="mb-[18px] text-[11px] tracking-[0.2em] text-brown uppercase">Zanim przyjdziesz &lt;b&gt;</h2>', '<dt class="text-ink">Do 6 osób</dt>'], false)
            ->assertDontSee('Dobrze wiedzieć');
    }

    public function test_an_empty_heading_leaves_the_card_with_its_facts_only(): void
    {
        $this->settings(['studio_facts_heading' => null]);

        $this->get('/pracownia')
            ->assertOk()
            ->assertDontSee('tracking-[0.2em] text-brown uppercase', false)
            ->assertDontSee('Dobrze wiedzieć')
            ->assertSee('<dt class="text-ink">Do 6 osób</dt>', false);
    }

    public function test_the_photos_come_from_zdjecia_stron_with_their_point_and_twelve_fill_whole_rows(): void
    {
        $this->settings(['page_photos' => [
            'studio-hero' => ['focus' => ['x' => 30, 'y' => 70]],
            'studio-gallery-2' => ['alt' => 'Filiżanka z talerzykiem w pracowni'],
            'studio-gallery-12' => ['path' => 'zdjecia/misa-laguna.webp', 'alt' => 'Misa w kolorze laguny', 'focus' => ['x' => 20, 'y' => 40]],
        ]]);

        $page = $this->get('/pracownia')->assertOk()
            ->assertSee('style="object-position: 30% 70%" class="block h-[clamp(320px,46vh,520px)]', false)
            ->assertSeeInOrder(['alt="Kubek z wbijanym tekstem"', 'alt="Filiżanka z talerzykiem w pracowni"', 'alt="Misa w kolorze laguny"'], false)
            ->assertSee('style="object-position: 20% 40%"', false)
            ->getContent();

        // Twelve photos make whole rows of two, three and four, so none is hidden at any width.
        $this->assertSame(12, substr_count($page, 'class="block aspect-square w-full rounded-[20px]'));
        $this->assertStringNotContainsString('max-sm:hidden', $page);
    }

    public function test_the_buttons_lead_to_the_workshops_and_firing_price_lists(): void
    {
        $this->get('/pracownia')
            ->assertSeeInOrder(['href="'.route('workshops.index').'"', 'Terminy i cennik', 'href="'.route('firing.index').'"', 'Wypalę Twoje prace'], false)
            ->assertDontSee('Zapytaj o warsztaty');
    }

    public function test_the_studio_page_waits_for_its_english_texts(): void
    {
        // Its texts are written in Polish (MA-301), so there is no English address until they are translated (MA-187).
        $this->get('/en/studio')->assertNotFound();
    }

    public function test_the_about_page_leads_here(): void
    {
        $this->get('/o-mnie')->assertSeeInOrder(['href="'.route('content.studio').'"', 'Zobacz, jak tworzę'], false);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
