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
            ->assertSeeInOrder(['Dostawa i opłaty', 'Darmowa wysyłka od', 'value="400"', 'InPost Paczkomat', 'value="16"', 'Pakowanie na prezent', 'value="12"'], false)
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
        $this->assertSame(1200, Setting::find('gift_wrap_price')->value);
    }

    public function test_company_details_are_tidied_up_and_the_nip_shows_in_the_footer(): void
    {
        $this->actingAs($this->owner)
            ->get('/panel/ustawienia')
            ->assertSeeInOrder(['Dane firmy — do regulaminu i dla operatora płatności', 'Firma z CEIDG', 'NIP', 'REGON', 'Adres firmy i do doręczeń', 'Adres do zwrotów albo Paczkomat']);

        $this->put('/panel/ustawienia/firma', [
            'company_name' => ' MellowAura Katarzyna Samborska ',
            'company_nip' => '111-111-11-11',
            'company_regon' => '123 456 789',
            'company_address' => 'ul. Wirtualna 1, 00-001 Warszawa',
            'return_address' => 'Paczkomat KRA01M',
            'company_bank_account' => 'PL 61 1090 1014 0000 0712 1981 2874',
            'company_vat_note' => '',
            'payment_operator' => 'PayPro S.A. (Przelewy24)',
        ])
            ->assertRedirect('/panel/ustawienia#firma')
            ->assertSessionHas('panel_status', 'Dane firmy zapisane. Regulamin i stopka już je pokazują.');

        $this->assertSame(
            ['MellowAura Katarzyna Samborska', '1111111111', '123456789', '61 1090 1014 0000 0712 1981 2874', null],
            collect(['company_name', 'company_nip', 'company_regon', 'company_bank_account', 'company_vat_note'])->map(fn (string $key) => Setting::find($key)->value)->all(),
        );
        $this->get('/sklep')->assertSee('Katarzyna Samborska · NIP 1111111111');

        $this->put('/panel/ustawienia/firma', ['company_nip' => '1234567890', 'company_regon' => '12345', 'company_bank_account' => '12 3456'])
            ->assertSessionHasErrorsIn('firma', [
                'company_nip' => 'Ten NIP się nie zgadza — sprawdź cyfry z CEIDG',
                'company_regon' => 'REGON ma 9 albo 14 cyfr',
                'company_bank_account' => 'Numer rachunku ma 26 cyfr — sprawdź, czy żadna nie uciekła',
            ]);
    }

    public function test_gift_wrapping_gets_its_price_here_and_an_empty_price_turns_it_off(): void
    {
        $this->actingAs($this->owner)
            ->put('/panel/ustawienia/dostawa', ['free_shipping_threshold' => '400', 'gift_wrap_price' => '14,50', 'shipping' => ['parcel_locker' => '16']])
            ->assertRedirect('/panel/ustawienia#dostawa');
        $this->assertSame(1450, Setting::find('gift_wrap_price')->value);

        $this->put('/panel/ustawienia/dostawa', ['free_shipping_threshold' => '400', 'gift_wrap_price' => 'dwanaście', 'shipping' => ['parcel_locker' => '16']])
            ->assertSessionHasErrorsIn('dostawa', ['gift_wrap_price' => 'Wpisz cenę, np. 12 — puste pole wyłącza pakowanie na prezent']);

        $this->put('/panel/ustawienia/dostawa', ['free_shipping_threshold' => '400', 'gift_wrap_price' => '', 'shipping' => ['parcel_locker' => '16']]);
        $this->assertNull(Setting::find('gift_wrap_price')->value);
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
