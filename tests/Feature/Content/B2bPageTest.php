<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_page_shows_the_cards_from_the_panel_and_asks_for_a_quote(): void
    {
        $this->settings(['contact_phone' => '+48 600 100 200']);
        $contact = 'href="'.e(route('content.contact', ['temat' => 'Kawiarnia / restauracja'])).'"';

        $response = $this->get('/ceramika-dla-gastronomii')
            ->assertOk()
            ->assertSee('<title>Ceramika dla kawiarni i restauracji | MellowAura</title>', false)
            ->assertSee('<meta name="description" content="Powtarzalne formy w ręcznej ceramice dla kawiarni i restauracji: minimum 12 sztuk, próbka przed serią, dosypka po stłuczkach, logo lub sygnatura.">', false)
            ->assertSee('focus-on-dark', false)
            ->assertSeeInOrder(['dla kawiarni, restauracji i hoteli', 'Naczynia, które<br>widać na zdjęciach<br>Waszych gości', 'Ręczna ceramika w powtarzalnej formie', $contact, 'Poproś o wycenę'], false)
            ->assertSeeInOrder(['Minimum 12 sztuk', 'Od dwunastu robi się sens', 'Próbka przed serią', 'Dosypka po stłuczkach', 'Logo lub sygnatura', 'Faktura i przelew z terminem 14 dni.'])
            ->assertSeeInOrder(['Prowadzicie lokal w Krakowie?', 'Przywiozę próbki', 'href="https://wa.me/48600100200"', $contact, 'Umów spotkanie'], false);

        $service = $this->structuredData($response->getContent())->firstWhere('@type', 'Service');
        $this->assertSame(['Ceramika dla kawiarni i restauracji', route('content.b2b'), 'Kraków'], [$service['name'], $service['url'], $service['areaServed']['name']]);
        $this->assertArrayNotHasKey('hasOfferCatalog', $service);
    }

    public function test_empty_cards_and_texts_are_left_out(): void
    {
        $this->settings(['b2b_facts' => [['title' => '', 'text' => 'Bez nagłówka']], 'text_b2b_lead' => null, 'text_b2b_cta_heading' => null, 'text_b2b_cta' => null]);

        $this->get('/ceramika-dla-gastronomii')
            ->assertOk()
            ->assertSee('<meta name="description" content="Powtarzalne formy w ręcznej ceramice dla kawiarni i restauracji.">', false)
            ->assertDontSee('Bez nagłówka')
            ->assertDontSee('Ręczna ceramika w powtarzalnej formie')
            ->assertSee('Porozmawiajmy o Waszym lokalu')
            ->assertDontSee('wa.me');
    }

    public function test_kasia_edits_the_cards_and_the_sentences_in_the_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/panel/tresci')
            ->assertSeeInOrder(['Dla kawiarni i restauracji — karty', 'value="Minimum 12 sztuk"', 'value="Logo lub sygnatura"', 'Nowa karta, np. Próbka przed serią'], false);

        $this->put('/panel/tresci/gastronomia', ['b2b' => [
            ['title' => 'Minimum 24 sztuki', 'text' => 'Od tylu robię serię.'],
            ['title' => 'Próbka przed serią', 'text' => '', 'remove' => '1'],
            ['title' => '', 'text' => ''],
        ]])
            ->assertRedirect('/panel/tresci#gastronomia')
            ->assertSessionHas('panel_status', 'Karty dla lokali zapisane');

        $this->assertEquals([['title' => 'Minimum 24 sztuki', 'text' => 'Od tylu robię serię.']], Setting::find('b2b_facts')->value);

        $this->put('/panel/tresci/teksty', ['text_b2b_lead' => 'Kubki do kawiarni.', 'text_b2b_cta_heading' => 'Macie kawiarnię?', 'text_b2b_cta' => '']);
        $this->get('/ceramika-dla-gastronomii')
            ->assertSee('dla kawiarni i restauracji: minimum 24 sztuki.">', false)
            ->assertSeeInOrder(['Kubki do kawiarni.', 'Minimum 24 sztuki', 'Macie kawiarnię?'])
            ->assertDontSee('Przywiozę próbki');

        $this->followingRedirects()
            ->put('/panel/tresci/gastronomia', ['b2b' => [['title' => str_repeat('a', 41)]]])
            ->assertSee('Nagłówek karty zmieszczę do 40 znaków');
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
