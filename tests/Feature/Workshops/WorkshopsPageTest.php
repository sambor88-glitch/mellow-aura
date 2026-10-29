<?php

namespace Tests\Feature\Workshops;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Workshops\Models\WorkshopDate;
use Carbon\CarbonImmutable;
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

    public function test_the_page_shows_the_price_list_from_the_panel_and_how_to_sign_up(): void
    {
        $this->settings(['contact_phone' => '+48 600 100 200']);

        $response = $this->get('/warsztaty-ceramiczne-krakow')
            ->assertOk()
            ->assertSee('<title>Warsztaty ceramiczne Kraków — kameralnie, do 6 osób</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('workshops.index').'">', false)
            ->assertSeeInOrder(['warsztaty &middot; cennik', 'Zanurz dłonie<br>w glinie', 'Ceny zawierają glinę, narzędzia, szkliwa i wypały.'], false)
            ->assertSeeInOrder(['Lepienie kubków', '220 zł', 'os.', '2 godziny', 'grupa do 6 osób', 'Pierwszy raz w glinie', 'Glina i wszystkie narzędzia', 'Herbata i coś słodkiego'])
            ->assertSeeInOrder(['Warsztat dla pary', '390 zł', 'para', 'Rodzinnie z dzieckiem', '260 zł', 'dorosły + dziecko'])
            ->assertSeeInOrder(['Jak się zapisać', 'Pracownia: Kraków, okolice Błoń Krakowskich. Dokładny adres podaję po zapisie.'])
            ->assertSee('href="https://wa.me/48600100200"', false)
            ->assertSee('href="'.e(route('content.contact', ['temat' => 'Warsztaty i terminy'])).'"', false)
            // workshop_booking counts bookings on the site (MA-140), not a message on WhatsApp or through the form.
            ->assertDontSee('data-analytics-click', false)
            ->assertDontSee('Najbliższe terminy');

        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('workshops.index'), '/').'"\s+aria-current="page"/', $response->getContent());

        $service = $this->structuredData($response->getContent())->firstWhere('@type', 'Service');
        $this->assertSame(['Warsztaty ceramiczne w Krakowie', route('workshops.index'), url('/').'/#business', 'Kraków'], [$service['name'], $service['url'], $service['provider']['@id'], $service['areaServed']['name']]);
        $this->assertSame([
            ['Lepienie kubków', '220.00', 'os.'],
            ['Szkliwienie i malowanie', '160.00', 'os.'],
            ['Sesja indywidualna 1:1', '420.00', 'os.'],
            ['Warsztat dla pary', '390.00', 'para'],
            ['Rodzinnie z dzieckiem', '260.00', 'dorosły + dziecko'],
        ], $this->serviceOffers($service));
    }

    public function test_the_upcoming_dates_come_from_the_calendar_with_free_seats_and_a_link_to_the_booking_form(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Warsaw'));
        WorkshopDate::factory()->create(['workshop_code' => 'glazing', 'starts_at' => '2026-11-03 18:00', 'ends_at' => '2026-11-03 20:00', 'seats' => 5]);
        $first = WorkshopDate::factory()->create(['workshop_code' => 'hand_building', 'starts_at' => '2026-10-31 11:00', 'ends_at' => '2026-10-31 13:30', 'seats' => 1]);
        WorkshopDate::factory()->create(['workshop_code' => 'hand_building', 'starts_at' => '2026-11-07 11:00', 'ends_at' => null, 'seats' => 3]);
        WorkshopDate::factory()->create(['workshop_code' => 'hand_building', 'starts_at' => '2026-10-26 10:00', 'seats' => 4]);
        WorkshopDate::factory()->create(['workshop_code' => 'removed_from_price_list', 'starts_at' => '2026-11-10 11:00', 'seats' => 4]);

        $response = $this->get('/warsztaty-ceramiczne-krakow')
            ->assertOk()
            ->assertSeeInOrder([
                'Najbliższe terminy',
                '<time datetime="2026-10-31T11:00:00+01:00">sob. 31 października</time>', '11:00 – 13:30 &middot; Lepienie kubków', 'ostatnie miejsce', '220 zł / os.',
                'wt. 3 listopada', '18:00 – 20:00 &middot; Szkliwienie i malowanie', '5 wolnych miejsc', '160 zł / os.',
                'sob. 7 listopada', '11:00 &middot; Lepienie kubków', '3 wolne miejsca',
                'Jak się zapisać',
            ], false)
            ->assertSee('href="'.route('workshops.booking.create', $first).'" aria-label="Wybieram: Lepienie kubków, sob. 31 października, 11:00 – 13:30"', false)
            ->assertDontSee('pon. 26 października')
            ->assertDontSee('10 listopada');

        $course = $this->structuredData($response->getContent())->firstWhere('@type', 'Course');
        $this->assertSame(['Warsztaty ceramiczne w Krakowie', url('/').'/#business'], [$course['name'], $course['provider']['@id']]);
        $this->assertSame([
            ['Lepienie kubków', '2026-10-31T11:00:00+01:00', '2026-10-31T13:30:00+01:00', '220.00', 'https://schema.org/InStock'],
            ['Szkliwienie i malowanie', '2026-11-03T18:00:00+01:00', '2026-11-03T20:00:00+01:00', '160.00', 'https://schema.org/InStock'],
            ['Lepienie kubków', '2026-11-07T11:00:00+01:00', null, '220.00', 'https://schema.org/InStock'],
        ], array_map(fn (array $instance) => [$instance['name'], $instance['startDate'], $instance['endDate'] ?? null, $instance['offers']['price'], $instance['offers']['availability']], $course['hasCourseInstance']));
        // The city only — the studio's address never goes into the markup.
        $this->assertSame(['@type' => 'PostalAddress', 'addressLocality' => 'Kraków', 'addressCountry' => 'PL'], $course['hasCourseInstance'][0]['location']['address']);
    }

    public function test_free_seats_are_counted_in_polish(): void
    {
        $labels = array_map(fn (int $seats) => (new WorkshopDate(['seats' => $seats]))->seatsLeftLabel(), [0, 1, 2, 4, 5, 12, 22, 25]);

        $this->assertSame(['brak miejsc', 'ostatnie miejsce', '2 wolne miejsca', '4 wolne miejsca', '5 wolnych miejsc', '12 wolnych miejsc', '22 wolne miejsca', '25 wolnych miejsc'], $labels);
    }

    public function test_without_dates_there_is_no_dates_section_and_no_course_markup(): void
    {
        WorkshopDate::factory()->create(['starts_at' => now()->subDay()]);

        $response = $this->get('/warsztaty-ceramiczne-krakow')->assertOk()->assertDontSee('Najbliższe terminy');

        $this->assertNull($this->structuredData($response->getContent())->firstWhere('@type', 'Course'));
    }

    public function test_the_studio_photos_from_the_panel_show_with_a_link_to_the_studio(): void
    {
        $this->settings([
            'studio_hero_image' => 'zdjecia/misa-laguna.webp',
            'studio_gallery' => [
                ['path' => 'zdjecia/nie-ma-takiego.webp', 'alt' => 'Zgubione zdjęcie'],
                ['path' => 'zdjecia/kubek-cappuccino.webp', 'alt' => 'Kubek malowany ręcznie'],
                ['path' => 'zdjecia/kasia-talerz-zloto.webp', 'alt' => 'Kasia z talerzem'],
                ['path' => 'zdjecia/kadzielnica-pszczoly.webp', 'alt' => 'Kadzielnica'],
                ['path' => 'zdjecia/kubek-cappuccino.webp', 'alt' => 'Czwarte'],
                ['path' => 'zdjecia/kubek-cappuccino.webp', 'alt' => 'Piąte — już na /pracownia'],
            ],
        ]);

        $this->get('/warsztaty-ceramiczne-krakow')
            // Without Vite in tests a photo's address is empty, so the hero is found by its size.
            ->assertSeeInOrder(['Zanurz dłonie<br>w glinie', 'h-[clamp(240px,40vh,460px)]', 'Jak się zapisać', 'Zobacz pracownię', 'alt="Kubek malowany ręcznie"', 'alt="Kasia z talerzem"', 'alt="Kadzielnica"', 'alt="Czwarte"'], false)
            ->assertSee('href="'.route('content.studio').'"', false)
            ->assertDontSee('Zgubione zdjęcie')
            ->assertDontSee('Piąte');
    }

    public function test_without_studio_photos_the_page_leaves_them_out(): void
    {
        $this->settings(['studio_hero_image' => 'zdjecia/nie-ma-takiego.webp', 'studio_gallery' => [['path' => 'zdjecia/nie-ma-takiego.webp', 'alt' => 'Zgubione']]]);

        $this->get('/warsztaty-ceramiczne-krakow')->assertDontSee('h-[clamp(240px,40vh,460px)]', false)->assertDontSee('Zobacz pracownię');
    }

    public function test_the_contact_form_starts_the_message_from_the_link(): void
    {
        $this->get('/kontakt?temat=Warsztaty+i+terminy&wiadomosc='.urlencode('Chcę się zapisać: <b>Lepienie</b>'))
            ->assertSee('Chcę się zapisać: &lt;b&gt;Lepienie&lt;/b&gt;</textarea>', false);
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
            ->assertSee('300 zł')
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
        $this->get('/')->assertSeeTextInOrder(['Zanurz dłonie w glinie', 'Lepienie kubków'])->assertSee('href="'.route('workshops.index').'"', false);
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
