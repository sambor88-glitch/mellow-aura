<?php

namespace Tests\Feature\Content;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_studio_page_shows_the_texts_facts_and_photos_from_the_panel_without_the_address(): void
    {
        $this->settings(['studio_address' => 'ul. Tajna 7, Kraków']);

        $this->get('/pracownia')
            ->assertOk()
            ->assertSee('<title>Pracownia w Krakowie — ciepło, empatia, cierpliwość</title>', false)
            ->assertSee('<meta name="description" content="Kameralne home studio w Krakowie: jeden stół, do sześciu osób, dwa wypały w cenie warsztatu.">', false)
            ->assertSee('<link rel="canonical" href="'.route('content.studio').'">', false)
            ->assertSeeInOrder(['pracownia &middot; kraków', 'Kameralna pracownia. Ciepło, empatia, cierpliwość.'], false)
            ->assertSee('from-scrim/72', false)
            ->assertSeeInOrder([
                'Warsztaty, które uczą nie tylko ceramiki',
                'Przy jednym stole siada najwyżej sześć osób',
                'Dokładny adres podaję po zapisaniu się',
            ])
            ->assertSeeInOrder(['dobrze wiedzieć', 'Do 6 osób', 'Przy jednym stole, bez tłoku.', 'Wysyłka prac', 'Jeśli nie możesz przyjechać — wyślę.'])
            ->assertSeeInOrder(['alt="Kubek z wbijanym tekstem"', 'alt="Filiżanka na talerzyku z malowaną lawendą"'], false)
            ->assertDontSee('ul. Tajna 7');
    }

    public function test_a_missing_photo_or_an_empty_fact_is_left_out(): void
    {
        $this->settings([
            'studio_hero_image' => 'zdjecia/nie-ma-takiego.webp',
            'studio_facts' => [['title' => 'Do 4 osób', 'text' => ''], ['title' => '', 'text' => 'Bez tytułu'], 'nie fakt'],
            'studio_gallery' => [['path' => 'zdjecia/nie-ma-takiego.webp', 'alt' => 'Zgubione zdjęcie'], ['path' => 'zdjecia/misa-laguna.webp', 'alt' => 'Misa w kolorze laguny']],
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

    public function test_the_buttons_lead_to_the_workshops_and_firing_price_lists(): void
    {
        $this->get('/pracownia')
            ->assertSeeInOrder(['href="'.route('workshops.index').'"', 'Terminy i cennik', 'href="'.route('firing.index').'"', 'Wypalę Twoje prace'], false)
            ->assertDontSee('Zapytaj o warsztaty');
    }

    public function test_the_about_page_leads_here(): void
    {
        $this->get('/o-mnie')->assertSeeInOrder(['href="'.route('content.studio').'"', 'Zobacz jak tworzę'], false);
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
}
