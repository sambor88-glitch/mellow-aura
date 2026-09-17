<?php

namespace Tests\Feature\Workshops;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_page_shows_the_price_list_from_the_panel_and_how_to_sign_up_before_booking_online(): void
    {
        $this->settings(['contact_phone' => '+48 600 100 200']);

        $response = $this->get('/warsztaty-ceramiczne-krakow')
            ->assertOk()
            ->assertSee('<title>Warsztaty ceramiczne Kraków — kameralnie, do 6 osób</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('workshops.index').'">', false)
            ->assertSeeInOrder(['warsztaty &middot; cennik', 'Zanurz dłonie<br>w glinie', 'Ceny zawierają glinę, narzędzia, szkliwa i wypały.'], false)
            ->assertSeeInOrder(['Lepienie z ręki', '220,00 zł', 'os.', '2,5 godziny', 'grupa do 6 osób', 'Pierwszy raz w glinie', 'Glina i wszystkie narzędzia', 'Herbata i coś słodkiego'])
            ->assertSeeInOrder(['Warsztat dla pary', '390,00 zł', 'para', 'Rodzinnie z dzieckiem', '260,00 zł', 'dorosły + dziecko'])
            ->assertSeeInOrder(['Jak się zapisać', 'Pracownia: Kraków, okolice Błoń Krakowskich. Dokładny adres podaję po zapisie.'])
            ->assertSee('href="https://wa.me/48600100200"', false)
            ->assertSee('href="'.e(route('content.contact', ['temat' => 'Warsztaty i terminy'])).'"', false)
            // Until workshops are booked on the site, writing about one counts as workshop_booking in Google Analytics.
            ->assertSee('data-analytics-click="'.e(json_encode(['name' => 'workshop_booking', 'params' => ['method' => 'whatsapp']])).'"', false)
            ->assertSee('data-analytics-click="'.e(json_encode(['name' => 'workshop_booking', 'params' => ['method' => 'contact_form']])).'"', false)
            ->assertDontSee('Najbliższe terminy');

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('workshops.index'), '/').'"\s+aria-current="page"/', $response->getContent());

        $service = $this->structuredData($response->getContent())->firstWhere('@type', 'Service');
        $this->assertSame(['Warsztaty ceramiczne w Krakowie', route('workshops.index'), url('/').'/#business', 'Kraków'], [$service['name'], $service['url'], $service['provider']['@id'], $service['areaServed']['name']]);
        $this->assertSame([
            ['Lepienie z ręki', '220.00', 'os.'],
            ['Szkliwienie i malowanie', '160.00', 'os.'],
            ['Sesja indywidualna 1:1', '420.00', 'os.'],
            ['Warsztat dla pary', '390.00', 'para'],
            ['Rodzinnie z dzieckiem', '260.00', 'dorosły + dziecko'],
        ], $this->serviceOffers($service));
    }

    public function test_a_workshop_without_a_name_or_price_and_an_empty_fact_are_left_out(): void
    {
        $this->settings([
            'workshop_types' => [
                ['name' => 'Koło garncarskie', 'price_gross' => 30000, 'unit_label' => null, 'duration_label' => '', 'includes' => []],
                ['name' => '', 'price_gross' => 10000],
                ['name' => 'Bez ceny'],
            ],
        ]);

        $this->get('/warsztaty-ceramiczne-krakow')
            ->assertOk()
            ->assertSee('Koło garncarskie')
            ->assertSee('300,00 zł')
            ->assertDontSee('Bez ceny')
            ->assertDontSee('wa.me', false);
    }

    public function test_the_contact_form_opens_with_the_topic_from_the_link(): void
    {
        $this->get('/kontakt?temat=Warsztaty+i+terminy')->assertSee('<option value="Warsztaty i terminy" selected>', false);
        $this->get('/kontakt?temat=Coś+innego')->assertDontSee('selected>', false);
    }

    public function test_the_studio_and_home_pages_lead_here(): void
    {
        $this->get('/pracownia')->assertSeeInOrder(['href="'.route('workshops.index').'"', 'Terminy i cennik'], false);
        $this->get('/')->assertSeeInOrder(['Zanurz dłonie w glinie', 'Lepienie z ręki'])->assertSee('href="'.route('workshops.index').'"', false);
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
     * The offers in a page's Service markup as [name, price, unit].
     *
     * @return list<array{string, string, ?string}>
     */
    private function serviceOffers(array $service): array
    {
        return array_map(fn (array $offer) => [$offer['itemOffered']['name'], $offer['price'], $offer['priceSpecification']['unitText'] ?? null], $service['hasOfferCatalog']['itemListElement']);
    }
}
