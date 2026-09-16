<?php

namespace Tests\Feature\Content;

use App\Modules\Content\Actions\AddServiceExample;
use App\Modules\Content\Enums\Service;
use App\Modules\Content\Models\ServiceExample;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServicePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_scarf_page_shows_the_texts_steps_and_prices_from_the_panel(): void
    {
        $response = $this->get('/z-twojej-apaszki')
            ->assertOk()
            ->assertSee('<title>Opaska z Twojej apaszki — szyję z Twojej tkaniny</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('content.scarf').'">', false)
            ->assertSeeInOrder(['usługa &middot; z Twojej tkaniny', "Apaszka babci\nnie musi leżeć\nw szafie", 'Przyślij mi tkaninę, która coś dla Ciebie znaczy', 'Szyję z tkanin z drugiego obiegu'], false)
            ->assertSeeInOrder(['href="'.route('content.contact', ['temat' => 'Zamówienie indywidualne']).'"', 'Napisz, co chcesz przysłać', 'href="'.route('content.imprint').'"', 'Odcisk Twojej rośliny →'], false)
            ->assertSeeInOrder(['01', 'Wysyłasz zdjęcie tkaniny', 'Powiem od razu', '04', 'Szyję i odsyłam'])
            ->assertSee('Kod Paczkomatu, do którego wyślesz paczkę, podam w wiadomości.')
            ->assertSeeInOrder(['Co wychodzi z jednej apaszki', 'Opaska szeroka, pikowana', 'z apaszki 65 × 65 cm zostaje zapas', '119,00 zł', 'Zestaw: opaska i dwie scrunchies', '199,00 zł'])
            ->assertSee('Wysyłka tkaniny do mnie na Twój koszt')
            ->assertSee('dlaczego to robię')
            // No crossed-out price: services keep no price history for the lowest price from 30 days.
            ->assertDontSee('239,00 zł')
            ->assertDontSee('id="przed-i-po"', false)
            ->assertDontSee('Szybciej na WhatsAppie');

        $service = $this->structuredData($response->getContent())->firstWhere('@type', 'Service');
        $this->assertSame(['Opaska z Twojej apaszki', route('content.scarf')], [$service['name'], $service['url']]);
        $this->assertSame(
            [['Opaska szeroka, pikowana', '119.00', 'PLN'], ['Scrunchie, rozmiar do wyboru', '69.00', 'PLN'], ['Zestaw: opaska i dwie scrunchies', '199.00', 'PLN']],
            array_map(fn (array $offer) => [$offer['itemOffered']['name'], $offer['price'], $offer['priceCurrency']], $service['hasOfferCatalog']['itemListElement']),
        );
    }

    public function test_the_imprint_page_shows_its_own_texts_and_the_parcel_locker_code(): void
    {
        $this->settings(['parcel_locker_code' => 'KRA01M', 'contact_phone' => '+48 600 100 200']);

        $this->get('/odcisk-twojej-rosliny')
            ->assertOk()
            ->assertSee('<title>Talerz z odciskiem Twojego kwiatu — pamiątka na lata</title>', false)
            ->assertSeeInOrder(["Kwiat z bukietu\nzostanie w glinie", 'Przyślij zasuszoną roślinę', 'Napisz, co chcesz odcisnąć', 'href="'.route('content.scarf').'"'], false)
            ->assertSeeInOrder(['Szybciej na WhatsAppie:', 'href="https://wa.me/48600100200"', '+48 600 100 200</a>'], false)
            ->assertSeeInOrder(['Suszysz roślinę płasko', 'Dwa wypały i wysyłka', 'Paczki do mnie: Paczkomat KRA01M.'])
            ->assertSeeInOrder(['Na czym odciskam', 'Talerzyk deserowy 18 cm', '149,00 zł', 'Para talerzy na rocznicę', '329,00 zł'])
            ->assertSee('na co to zamawiają')
            ->assertDontSee('Opaska szeroka, pikowana');
    }

    public function test_empty_texts_steps_and_prices_are_left_out(): void
    {
        $this->settings([
            'text_scarf_heading' => null,
            'text_scarf_lead' => '',
            'text_scarf_lead_2' => null,
            'text_scarf_note' => null,
            'scarf_steps' => [['title' => '', 'text' => 'Bez nazwy'], 'nie krok'],
            'scarf_service_prices' => [['label' => 'Bez ceny', 'price_gross' => null], ['label' => '', 'price_gross' => 4321], ['label' => 'Opaska', 'note' => null, 'price_gross' => 12900]],
        ]);

        $this->get('/z-twojej-apaszki')
            ->assertOk()
            ->assertSee('tracking-[-0.02em] whitespace-pre-line">Z Twojej apaszki</h1>', false)
            ->assertDontSee('Przyślij mi tkaninę')
            ->assertDontSee('<ol', false)
            ->assertDontSee('Bez nazwy')
            ->assertDontSee('Kod Paczkomatu')
            ->assertDontSee('Bez ceny')
            ->assertDontSee('43,21 zł')
            ->assertSeeInOrder(['Opaska', '129,00 zł'])
            ->assertDontSee('Wysyłka tkaniny do mnie');
    }

    public function test_a_before_and_after_pair_shows_once_both_photos_are_there(): void
    {
        $add = app(AddServiceExample::class);
        $first = $add(Service::Scarf, UploadedFile::fake()->image('a.jpg', 400, 500), UploadedFile::fake()->image('b.jpg', 400, 500), ['caption' => 'Apaszka babci z lat 70.', 'before_alt' => null, 'after_alt' => 'Opaska w granatowe kwiaty']);
        $add(Service::Imprint, UploadedFile::fake()->image('c.jpg'), UploadedFile::fake()->image('d.jpg'), ['caption' => 'Wiązanka z wesela Oli']);
        ServiceExample::create(['service' => Service::Scarf, 'caption' => 'Jeszcze bez zdjęcia „po”', 'sort_order' => 3])
            ->addMedia(UploadedFile::fake()->image('e.jpg'))->toMediaCollection('before');
        $first = $first->fresh();

        $this->get('/z-twojej-apaszki')
            ->assertOk()
            ->assertSeeInOrder(['id="przed-i-po"', 'Przeciągnij uchwyt na zdjęciu'], false)
            ->assertSeeInOrder([
                'src="'.$first->getFirstMediaUrl('after', 'card').'" alt="Opaska w granatowe kwiaty"',
                'src="'.$first->getFirstMediaUrl('before', 'card').'" alt="Tkanina przysłana do przeróbki"',
                'role="slider"',
                'Apaszka babci z lat 70.',
            ], false)
            ->assertDontSee('Jeszcze bez zdjęcia „po”')
            ->assertDontSee('Wiązanka z wesela Oli');

        $this->get('/odcisk-twojej-rosliny')->assertSee('Wiązanka z wesela Oli')->assertSee('alt="Roślina przysłana do odcisku"', false);
    }

    public function test_the_home_page_shop_and_footer_lead_to_both_services(): void
    {
        foreach (['/', '/sklep'] as $page) {
            $this->get($page)
                ->assertSeeInOrder(['href="'.route('content.scarf').'"', 'Z Twojej apaszki'], false)
                ->assertSeeInOrder(['href="'.route('content.imprint').'"', 'Odcisk Twojej rośliny'], false);
        }
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
