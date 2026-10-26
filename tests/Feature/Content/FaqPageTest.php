<?php

namespace Tests\Feature\Content;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_page_shows_the_answers_and_the_delivery_prices_from_the_panel(): void
    {
        $this->settings(['return_address' => 'Paczkomat KRA01M', 'contact_phone' => '+48 600 100 200']);

        $this->get('/wysylka-i-pielegnacja')
            ->assertOk()
            ->assertSee('<title>Wysyłka, płatności i pielęgnacja ceramiki — FAQ</title>', false)
            ->assertSee('<meta name="description" content="Jak myć ceramikę ze złotem, czy można do zmywarki, ile trwa wysyłka, jak zapłacić BLIK-iem i jak wyglądają zwroty.">', false)
            ->assertSeeInOrder(['wysyłka, płatności, pielęgnacja', 'Częste pytania'])
            ->assertSeeInOrder(['<details name="faq"', 'Jak szybko wyślesz zamówienie?', 'Rzeczy, które są na stanie, pakuję w 3–5 dni roboczych.', 'Wysyłasz za granicę?'], false)
            ->assertSeeInOrder(['Wysyłka i zwroty', 'InPost Paczkomat', '1–2 dni robocze', '16,00 zł', 'Kurier InPost', '22,00 zł', 'Odbiór w pracowni', 'gratis'])
            ->assertSee('Od 400,00 zł za produkty każda wysyłka jest gratis.')
            ->assertSee('Zwroty odsyłasz na adres: Paczkomat KRA01M.')
            ->assertSee('href="'.route('content.terms').'#regulamin-12-prawo-odstapienia-od-umowy">regulaminie, w&nbsp;§12 i&nbsp;§13</a>', false)
            ->assertSeeInOrder(['Nie ma tu Twojego pytania?', 'Napisz na WhatsAppie — to najszybsza droga do mnie.', 'href="'.route('content.contact').'"'], false)
            ->assertDontSee('w stanie nienaruszonym');
    }

    public function test_an_empty_answer_price_or_address_does_not_show(): void
    {
        $this->settings([
            'faq_items' => [
                ['question' => 'Czy robisz talerze?', 'answer' => 'Tak, na zamówienie.'],
                ['question' => 'Pytanie bez odpowiedzi', 'answer' => ''],
                'nie pytanie',
            ],
            'free_shipping_threshold' => null,
        ]);

        $this->get('/wysylka-i-pielegnacja')
            ->assertOk()
            ->assertSee('Czy robisz talerze?')
            ->assertDontSee('Pytanie bez odpowiedzi')
            ->assertDontSee('nie pytanie')
            ->assertDontSee('każda wysyłka jest gratis')
            ->assertDontSee('Zwroty odsyłasz na adres')
            ->assertSee('Napisz do mnie — odpisuję zwykle tego samego dnia.');
    }

    public function test_an_answer_is_escaped(): void
    {
        $this->settings(['faq_items' => [['question' => 'Pytanie <b>', 'answer' => '<script>alert(1)</script>']]]);

        $this->get('/wysylka-i-pielegnacja')
            ->assertSee('Pytanie &lt;b&gt;', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_footer_and_the_terms_point_to_the_page(): void
    {
        $this->get('/kontakt')->assertSeeInOrder(['href="'.route('content.faq').'"', 'Wysyłka i zwroty'], false);

        $this->get('/regulamin')->assertSee('podane są na stronie <a href="'.route('content.faq').'">', false);
    }

    public function test_the_migration_corrects_the_prototype_answer_about_returns_but_keeps_a_rewritten_one(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_09_16_140000_correct_the_returns_answer_in_faq_items.php');
        $prototypeAnswer = 'Masz 14 dni na odesłanie rzeczy kupionej w sklepie, w stanie nienaruszonym. Nie dotyczy to zamówień indywidualnych i kubków z Twoim tekstem, bo powstały tylko dla Ciebie.';

        $this->settings(['faq_items' => [
            ['question' => 'Czy mogę zapłacić BLIK-iem?', 'answer' => 'Tak.'],
            ['question' => 'Zwroty i wymiany', 'answer' => $prototypeAnswer],
        ]]);
        $migration->up();

        $items = Setting::find('faq_items')->value;
        $this->assertSame('Tak.', $items[0]['answer']);
        $this->assertStringStartsWith('Masz 14 dni od odebrania paczki, żeby odstąpić od umowy', $items[1]['answer']);
        $this->assertSame('Zwroty i wymiany', $items[1]['question']);

        $this->settings(['faq_items' => [['question' => 'Zwroty', 'answer' => 'Moja własna odpowiedź.']]]);
        $migration->up();

        $this->assertSame('Moja własna odpowiedź.', Setting::find('faq_items')->value[0]['answer']);
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
