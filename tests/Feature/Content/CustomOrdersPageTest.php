<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomOrdersPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_page_shows_the_steps_from_the_panel_and_both_ways_to_write(): void
    {
        $this->settings(['contact_phone' => '+48 600 100 200']);

        $this->get('/zamowienia-indywidualne')
            ->assertOk()
            ->assertSee('<title>Zamówienia indywidualne — ceramika na zamówienie</title>', false)
            ->assertSee('Bezpłatny szkic i wycena w pięć dni, płatność BLIK-iem, realizacja około czterech tygodni.">', false)
            ->assertSee('<link rel="canonical" href="'.route('custom-orders.index').'">', false)
            ->assertSeeInOrder(['zamówienia indywidualne', 'Zaprojektujmy<br>to razem', 'Serwis na wesele, kubek z tekstem'], false)
            ->assertSeeInOrder(['Piszesz, czego szukasz', 'Dostajesz szkic i cenę', 'Do pięciu dni roboczych i za darmo.', 'Akceptujesz i płacisz BLIK-iem', 'Całość po akceptacji szkicu i ceny.', 'w dwóch ratach', 'Lepienie, dwa wypały, wysyłka', 'Przed wysyłką dostajesz zdjęcia gotowej pracy.', 'robię od nowa albo oddaję całą wpłatę.'])
            ->assertDontSee('Zaliczka')
            ->assertSee('alt="Kubek z wbijanym napisem"', false)
            ->assertSeeInOrder(['Opowiedz o pomyśle', 'Zdjęcia inspiracji — najłatwiej wysłać je na WhatsAppie', 'href="https://wa.me/48600100200"', 'Napisz na WhatsAppie', 'href="'.e(route('content.contact', ['temat' => 'Zamówienie indywidualne'])).'"', 'Napisz przez formularz', 'Woli Ci się pisać na WhatsAppie?'], false);
    }

    public function test_without_a_phone_or_steps_the_page_keeps_the_form_only(): void
    {
        $this->settings(['custom_order_steps' => [['title' => '', 'text' => 'Bez nazwy']], 'text_custom_orders_lead' => null]);

        $this->get('/zamowienia-indywidualne')
            ->assertOk()
            ->assertDontSee('<ol', false)
            ->assertDontSee('Bez nazwy')
            ->assertDontSee('Serwis na wesele, kubek z tekstem')
            ->assertDontSee('wa.me')
            ->assertSee('Zdjęcia inspiracji, jeśli je masz')
            ->assertSee('Napisz przez formularz');
    }

    public function test_the_home_page_shop_and_footer_lead_here(): void
    {
        foreach (['/', '/sklep'] as $page) {
            $this->get($page)->assertSee('href="'.route('custom-orders.index').'"', false);
        }
    }

    public function test_kasia_edits_the_steps_and_the_sentence_in_the_panel(): void
    {
        $owner = User::factory()->create();

        $this->put('/panel/tresci/zamowienia-indywidualne')->assertRedirect('/panel/logowanie');

        $this->actingAs($owner)
            ->get('/panel/tresci')
            ->assertOk()
            ->assertSeeInOrder(['Zamówienia indywidualne', 'Serwis na wesele', 'Dla kawiarni i restauracji'])
            ->assertSeeInOrder(['Zamówienia indywidualne — kroki', 'value="Piszesz, czego szukasz"', 'value="Lepienie, dwa wypały, wysyłka"', 'placeholder="Nowy krok"'], false);

        $this->put('/panel/tresci/zamowienia-indywidualne', ['move' => '1:up', 'steps' => [
            ['title' => ' Piszesz ', 'text' => 'Zdjęcia i termin.'],
            ['title' => 'Wycena', 'text' => ''],
            ['title' => 'Stary krok', 'text' => 'Do usunięcia.', 'remove' => '1'],
            ['title' => '', 'text' => ''],
        ]])
            ->assertRedirect('/panel/tresci#zamowienia-indywidualne')
            ->assertSessionHas('panel_status', 'Kolejność zmieniona. Klienci już ją widzą.');

        $this->assertEquals([['title' => 'Wycena', 'text' => null], ['title' => 'Piszesz', 'text' => 'Zdjęcia i termin.']], Setting::find('custom_order_steps')->value);

        $this->followingRedirects()
            ->put('/panel/tresci/zamowienia-indywidualne', ['steps' => [['title' => '', 'text' => 'Opis bez nazwy']]])
            ->assertSee('Nazwij ten krok, np. Dostajesz szkic i cenę');

        $this->put('/panel/tresci/teksty', ['text_custom_orders_lead' => 'Opowiedz mi o pomyśle.']);
        $this->get('/zamowienia-indywidualne')->assertSeeInOrder(['Opowiedz mi o pomyśle.', 'Wycena', 'Piszesz']);
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
