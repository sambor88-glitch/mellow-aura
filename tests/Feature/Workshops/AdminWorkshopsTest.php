<?php

namespace Tests\Feature\Workshops;

use App\Models\User;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkshopsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->owner = User::factory()->create();
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/panel/warsztaty')->assertRedirect('/panel/logowanie');
        $this->put('/panel/warsztaty')->assertRedirect('/panel/logowanie');
    }

    public function test_the_panel_shows_the_price_list_with_one_empty_row(): void
    {
        $this->actingAs($this->owner)
            ->get('/panel/warsztaty')
            ->assertOk()
            ->assertSee('>Warsztaty</a>', false)
            ->assertSeeInOrder(['Ceny zawierają glinę', 'value="Lepienie z ręki"', 'value="220"', 'value="os."', 'value="2,5 godziny"', 'Glina i wszystkie narzędzia', 'value="Rodzinnie z dzieckiem"', 'Nowy warsztat — nazwa'], false)
            ->assertSee('aria-label="Przesuń niżej: Lepienie z ręki"', false);
    }

    public function test_the_price_list_is_saved_with_a_new_workshop_and_without_a_removed_one(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/warsztaty', [
                'text_workshops_lead' => ' Wszystko w cenie. ',
                'workshops' => [
                    ['name' => 'Lepienie z ręki', 'price' => '230,50', 'unit_label' => 'os.', 'duration_label' => '2,5 godziny', 'group_label' => '', 'summary' => 'Płat i wałek.', 'includes' => "Glina\r\n\r\n Dwa wypały ", 'code' => 'hand_building', 'unit' => 'person'],
                    ['name' => 'Szkliwienie', 'price' => '160', 'code' => 'glazing', 'unit' => 'person', 'remove' => '1'],
                    ['name' => 'Koło garncarskie', 'price' => '300', 'unit_label' => 'os.', 'code' => '', 'unit' => ''],
                ],
            ])
            ->assertRedirect('/panel/warsztaty#cennik')
            ->assertSessionHas('panel_status', 'Zapisane. Cennik warsztatów już tak wygląda.');

        $this->assertSame('Wszystko w cenie.', Setting::find('text_workshops_lead')->value);
        $types = Setting::find('workshop_types')->value;
        $this->assertSame(['Lepienie z ręki', 'Koło garncarskie'], array_column($types, 'name'));
        $this->assertEquals([
            'code' => 'hand_building', 'name' => 'Lepienie z ręki', 'duration_label' => '2,5 godziny', 'group_label' => null, 'price_gross' => 23050,
            'unit' => 'person', 'unit_label' => 'os.', 'summary' => 'Płat i wałek.', 'includes' => ['Glina', 'Dwa wypały'],
        ], $types[0]);
        $this->assertSame(['kolo_garncarskie', 30000], [$types[1]['code'], $types[1]['price_gross']]);

        $this->get('/warsztaty-ceramiczne-krakow')->assertSeeInOrder(['Lepienie z ręki', '230,50 zł', 'Koło garncarskie', '300,00 zł'])->assertDontSee('Szkliwienie');
    }

    public function test_a_price_without_a_name_and_a_bad_price_are_explained_on_their_row(): void
    {
        $this->actingAs($this->owner)
            ->followingRedirects()
            ->put('/panel/warsztaty', ['workshops' => [
                ['name' => 'Lepienie z ręki', 'price' => 'dwieście'],
                ['name' => '', 'price' => '100'],
            ]])
            ->assertSee('Wpisz cenę, np. 220 albo 220,50')
            ->assertSee('Wpisz nazwę warsztatu do tej ceny')
            ->assertSee('value="dwieście"', false);

        $this->assertSame(22000, Setting::find('workshop_types')->value[0]['price_gross']);
    }

    public function test_an_arrow_moves_a_workshop(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/warsztaty', ['move' => '1:up', 'workshops' => [
                ['name' => 'Pierwszy', 'price' => '100'],
                ['name' => 'Drugi', 'price' => '200'],
            ]])
            ->assertSessionHas('panel_status', 'Kolejność zmieniona. Klienci już ją widzą.');

        $this->assertSame(['Drugi', 'Pierwszy'], array_column(Setting::find('workshop_types')->value, 'name'));
    }
}
