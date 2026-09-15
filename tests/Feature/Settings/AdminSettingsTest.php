<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
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
        $this->get('/panel/ustawienia')->assertRedirect('/panel/logowanie');
        $this->put('/panel/ustawienia/pracownia')->assertRedirect('/panel/logowanie');
    }

    public function test_the_page_shows_the_current_settings_in_three_cards(): void
    {
        $this->actingAs($this->owner)
            ->get('/panel/ustawienia')
            ->assertOk()
            ->assertSee('href="'.route('admin.settings.edit').'"', false)
            ->assertSeeInOrder(['Dostawa i opłaty', 'Darmowa wysyłka od', 'value="400"', 'InPost Paczkomat', 'value="16"'], false)
            ->assertSeeInOrder(['Dane pracowni', 'value="Kraków, okolice Błoń Krakowskich"', 'Dokładny adres'], false)
            ->assertSeeInOrder(['kafelki na „O mnie”', 'value="Glina"', 'Kamionka i szamot piaskowy'], false);
    }

    public function test_delivery_prices_and_the_free_shipping_threshold_are_saved(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/ustawienia/dostawa', [
                'free_shipping_threshold' => '350',
                'shipping' => ['parcel_locker' => '14,99', 'courier' => '22', 'studio_pickup' => '0'],
            ])
            ->assertRedirect('/panel/ustawienia#dostawa')
            ->assertSessionHas('panel_status', 'Zapisane. Koszyk już liczy według nowych cen.');

        $this->assertSame(35000, Setting::find('free_shipping_threshold')->value);
        $this->assertSame(
            [['parcel_locker', 'InPost Paczkomat', 1499], ['courier', 'Kurier InPost', 2200], ['studio_pickup', 'Odbiór w pracowni', 0]],
            collect(Setting::find('shipping_methods')->value)->map(fn (array $method) => [$method['code'], $method['label'], $method['price_gross']])->all(),
        );

        $this->put('/panel/ustawienia/dostawa', ['free_shipping_threshold' => '', 'shipping' => ['parcel_locker' => '']])
            ->assertSessionHasErrorsIn('dostawa', ['shipping.parcel_locker' => 'Wpisz cenę, np. 16 albo 16,50 — za darmo wpisz 0']);

        $this->put('/panel/ustawienia/dostawa', ['free_shipping_threshold' => '', 'shipping' => ['parcel_locker' => '16']]);
        $this->assertNull(Setting::find('free_shipping_threshold')->value);
    }

    public function test_studio_details_are_tidied_up_and_the_address_never_reaches_the_site(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/ustawienia/pracownia', [
                'contact_phone' => '600 100 200',
                'contact_email' => 'pracownia@example.com',
                'instagram_handle' => 'https://www.instagram.com/mellowaura/?igsh=abc',
                'facebook_url' => 'facebook.com/mellowaura',
                'google_business_profile_url' => '',
                'location_description' => 'Kraków, okolice Błoń Krakowskich',
                'studio_address' => 'ul. Przykładowa 7, 30-001 Kraków',
                'parcel_locker_code' => 'kra 01m',
                'footer_city' => 'Kraków, Polska',
            ])
            ->assertRedirect('/panel/ustawienia#pracownia')
            ->assertSessionHas('panel_status', 'Dane pracowni zapisane');

        $this->assertSame(
            ['+48 600 100 200', 'mellowaura', 'https://facebook.com/mellowaura', null, 'KRA01M'],
            collect(['contact_phone', 'instagram_handle', 'facebook_url', 'google_business_profile_url', 'parcel_locker_code'])
                ->map(fn (string $key) => Setting::find($key)->value)
                ->all(),
        );

        $this->get('/sklep')
            ->assertOk()
            ->assertSee('href="https://wa.me/48600100200"', false)
            ->assertSee('pracownia@example.com')
            ->assertSee('https://www.instagram.com/mellowaura/', false)
            ->assertDontSee('Przykładowa');
    }

    public function test_mistakes_come_back_to_their_card(): void
    {
        $mistakes = ['contact_email' => 'pracownia-example.com', 'parcel_locker_code' => 'Kraków 1', 'contact_phone' => '12'];

        $this->actingAs($this->owner)
            ->followingRedirects()
            ->put('/panel/ustawienia/pracownia', $mistakes)
            ->assertSeeInOrder(['Dane pracowni', 'Popraw zaznaczone pola, żeby zapisać.', 'Wpisz numer telefonu, np. 600 100 200', 'Wpisz kod Paczkomatu z aplikacji InPost, np. KRA01M']);

        $this->put('/panel/ustawienia/pracownia', $mistakes)
            ->assertRedirect('/panel/ustawienia#pracownia')
            ->assertSessionHasErrorsIn('pracownia', ['contact_email', 'parcel_locker_code', 'contact_phone']);

        $this->assertNull(Setting::find('contact_email')->value);
    }

    public function test_material_tiles_are_edited_removed_and_added(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/ustawienia/materialy', ['tiles' => [
                ['title' => 'Glina', 'text' => 'Opis gliny.'],
                ['title' => 'Jedwab', 'text' => 'Opis jedwabiu.', 'remove' => '1'],
                ['title' => 'Zmywarka', 'text' => ''],
                ['title' => '', 'text' => ''],
                ['title' => 'Atest szkliw', 'text' => 'Opis atestu.'],
            ]])
            ->assertRedirect('/panel/ustawienia#materialy');

        // MySQL stores JSON object keys in its own order, so only the content is compared.
        $this->assertEquals([
            ['title' => 'Glina', 'text' => 'Opis gliny.'],
            ['title' => 'Zmywarka', 'text' => null],
            ['title' => 'Atest szkliw', 'text' => 'Opis atestu.'],
        ], Setting::find('material_tiles')->value);

        $this->put('/panel/ustawienia/materialy', ['tiles' => [['title' => '', 'text' => 'Opis bez nazwy.']]])
            ->assertSessionHasErrorsIn('materialy', ['tiles.0.title' => 'Nazwij kafelek — np. Glina']);
    }
}
