<?php

namespace Tests\Feature\Firing;

use App\Models\User;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiringPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_page_shows_the_price_list_from_the_panel_and_how_to_report_a_batch(): void
    {
        Setting::query()->where('key', 'contact_phone')->update(['value' => json_encode('+48 600 100 200')]);

        $this->get('/wypal-ceramiki-krakow')
            ->assertOk()
            ->assertSee('<title>Wypał ceramiki Kraków — cennik wypałów na zlecenie</title>', false)
            ->assertSeeInOrder(['wypały na zlecenie', 'Wypalę Twoje prace', 'Lepisz w domu i nie masz pieca?'])
            ->assertSeeInOrder(['Wypał biskwitowy do 1000°C', 'liczony od litra zajętego miejsca', '40,00 zł / l', 'Cała półka na wyłączność', '180,00 zł', 'Szkliwienie przeze mnie'])
            ->assertSee('Wsad zbieram raz w tygodniu, zwykle w czwartek.')
            ->assertSeeInOrder(['Zgłoś wsad', 'href="https://wa.me/48600100200"', 'href="'.e(route('content.contact', ['temat' => 'Wypał moich prac'])).'"'], false);

        $this->get('/pracownia')->assertSeeInOrder(['Terminy i cennik', 'href="'.route('firing.index').'"', 'Wypalę Twoje prace'], false);
    }

    public function test_the_panel_saves_the_price_list_and_the_sentences(): void
    {
        $this->get('/panel/wypaly')->assertRedirect('/panel/logowanie');

        $this->actingAs(User::factory()->create())
            ->get('/panel/wypaly')
            ->assertOk()
            ->assertSeeInOrder(['Lepisz w domu', 'Wsad zbieram', 'value="Wypał biskwitowy do 1000°C"', 'value="40"', 'value="/ l"', 'Nowa usługa — nazwa'], false);

        $this->put('/panel/wypaly', [
            'text_kiln_lead' => 'Nie masz pieca?',
            'text_kiln_note' => '',
            'prices' => [
                ['label' => 'Wypał biskwitowy', 'price' => '45', 'unit_label' => '/ l', 'note' => '', 'code' => 'bisque_firing', 'unit' => 'litre'],
                ['label' => 'Cała półka', 'price' => '180', 'code' => 'private_shelf', 'remove' => '1'],
                ['label' => 'Wypał raku', 'price' => '90,50', 'unit_label' => '', 'note' => 'w ogrodzie', 'code' => '', 'unit' => ''],
                ['label' => '', 'price' => ''],
            ],
        ])
            ->assertRedirect('/panel/wypaly#cennik')
            ->assertSessionHas('panel_status', 'Zapisane. Cennik wypałów już tak wygląda.');

        $this->assertSame('Nie masz pieca?', Setting::find('text_kiln_lead')->value);
        $this->assertNull(Setting::find('text_kiln_note')->value);
        $this->assertEquals([
            ['code' => 'bisque_firing', 'label' => 'Wypał biskwitowy', 'note' => null, 'price_gross' => 4500, 'unit' => 'litre', 'unit_label' => '/ l'],
            ['code' => 'wypal_raku', 'label' => 'Wypał raku', 'note' => 'w ogrodzie', 'price_gross' => 9050, 'unit' => null, 'unit_label' => null],
        ], Setting::find('kiln_prices')->value);

        $this->followingRedirects()
            ->put('/panel/wypaly', ['prices' => [['label' => 'Wypał', 'price' => 'drogo']]])
            ->assertSee('Wpisz cenę, np. 40 albo 40,50');

        $this->get('/wypal-ceramiki-krakow')->assertSeeInOrder(['Wypał biskwitowy', '45,00 zł / l', 'Wypał raku', '90,50 zł'])->assertDontSee('Cała półka');
    }
}
