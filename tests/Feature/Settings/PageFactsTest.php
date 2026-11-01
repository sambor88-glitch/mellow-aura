<?php

namespace Tests\Feature\Settings;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use App\Modules\Workshops\Http\Controllers\BookingController;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Facts on the pages come from the panel (MA-297): with the starting values the pages read as before,
 * an empty setting takes its sentence out, and the payment lists follow PaymentMethod::for.
 */
class PageFactsTest extends TestCase
{
    use RefreshDatabase;

    private const FACTS = [
        'text_home_lead_mug', 'text_home_fact_handmade', 'text_home_materials', 'text_home_card_b2b',
        'text_shop_intro_note', 'text_reply_time', 'text_blik_time', 'text_checkout_packing',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_with_the_starting_values_the_pages_read_as_before(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('dwie dziedziny w jednej krakowskiej pracowni. Do tego kubki z napisem wbijanym stemplem, którego nie zetrze żadna zmywarka.')
            ->assertSeeInOrder(['100%', 'ręczna robota', 'BLIK', 'płatność w 10 sekund'])
            ->assertSeeInOrder(['glina', 'jedwab', 'len', 'złoto 24k', 'surowość'])
            ->assertSee('Powtarzalne formy, które wytrzymują zmywarkę i codzienny serwis.');

        $this->get('/sklep')
            ->assertSee('Wszystko, co stoi teraz na półce w pracowni. Ceramika jest wypalona i gotowa do wysyłki — jeśli czegoś nie ma, znaczy, że już pojechało do kogoś.');

        $this->get('/produkt/'.$this->variant()->product->slug)
            ->assertSee('zapłacisz w 10 sekund kodem z aplikacji banku');

        $this->get('/kubek-z-napisem')
            ->assertSee('<meta name="description" content="Zaprojektuj kubek z własnym napisem: litery wbijane stemplem w glinę, trzy rozmiary, sześć kolorów szkliwa. Prezent, którego nie ma nikt inny. Kraków.">', false)
            ->assertSee('zapłacisz w 10 sekund');

        $this->get('/kontakt')
            ->assertSee('<meta name="description" content="Napisz do mnie przez formularz. Odpisuję zwykle tego samego dnia.">', false);
        $this->get('/wysylka-i-pielegnacja')->assertSee('Napisz do mnie — odpisuję zwykle tego samego dnia.');

        $this->postJson('/koszyk', ['variant_id' => $this->variant('Patery')->id]);
        $this->get('/zamowienie')->assertSee('Ceramikę zawijam w wióry i podwójny karton');
    }

    public function test_an_empty_setting_takes_its_sentence_out_without_a_dangling_separator(): void
    {
        Setting::query()->whereIn('key', self::FACTS)->update(['value' => json_encode(null)]);

        $home = $this->get('/')->assertOk()
            ->assertSee('dwie dziedziny w jednej krakowskiej pracowni.</p>', false)
            ->assertDontSee('zmywark')
            ->assertDontSee('>100%</strong>', false)
            ->assertDontSee('ręczna robota')
            ->assertDontSee('płatność w')
            ->assertDontSee('złoto 24k')
            // The b2b card keeps its heading and loses only the empty sentence.
            ->assertSee('Ceramika dla kawiarni i restauracji');
        $this->assertSame(1, substr_count($home->getContent(), 'data-aura-row='));

        $this->get('/sklep')->assertSee('Wszystko, co stoi teraz na półce w pracowni.</p>', false)->assertDontSee('wypalona');

        $this->get('/produkt/'.$this->variant()->product->slug)
            ->assertSee('zapłacisz kodem z aplikacji banku')
            ->assertDontSee('10 sekund');

        $this->get('/kubek-z-napisem')->assertSee('płatność kodem z aplikacji banku')->assertDontSee('10 sekund');

        $this->get('/kontakt')->assertSee('<meta name="description" content="Napisz do mnie przez formularz.">', false);
        $this->get('/wysylka-i-pielegnacja')->assertSee('Napisz do mnie.')->assertDontSee('tego samego dnia');

        $this->followingRedirects()
            ->post('/kontakt', ['name' => 'Ola', 'email' => 'ola@example.com', 'topic' => 'Warsztaty i terminy', 'message' => 'Dzień dobry', 'website' => 'https://spam.example'])
            ->assertSee('Wiadomość poszła.</p>', false);

        $this->postJson('/koszyk', ['variant_id' => $this->variant('Patery')->id]);
        $this->get('/zamowienie')->assertOk()->assertDontSee('wióry');
    }

    public function test_kasias_polish_sentences_stay_off_the_english_pages(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertDontSee('zmywarka')
            ->assertDontSee('dishwasher')
            ->assertDontSee('złoto 24k')
            ->assertDontSee('24k')
            ->assertSeeInOrder(['clay', 'silk', 'linen', 'gold']);

        $this->get('/en/shop')->assertSee('Everything on the studio shelf right now.</p>', false)->assertDontSee('wypalona');
        // The meta description; the contact lead from the panel is a separate setting.
        $this->get('/en/contact')->assertDontSee('przez formularz. Odpisuję');
    }

    public function test_the_payment_lists_follow_the_checkout(): void
    {
        $titles = fn (array $methods) => collect($methods)->map(fn (PaymentMethod $method) => $method->title())->join(' · ');

        $this->assertStringContainsString('<span>'.$titles(PaymentMethod::for('PLN')).'</span>', $this->get('/sklep')->getContent());
        App::setLocale('en');
        $english = '<span>'.$titles(PaymentMethod::for('EUR')).'</span>';
        App::setLocale('pl');
        $this->assertStringContainsString($english, $this->get('/en/shop')->getContent());

        WorkshopDate::factory()->create(['workshop_code' => 'glazing', 'starts_at' => now()->addWeek(), 'seats' => 5]);
        $this->assertSame([PaymentMethod::Blik, PaymentMethod::Card, PaymentMethod::OnlineTransfer], BookingController::paymentMethods());
        $this->get('/warsztaty-ceramiczne-krakow')
            ->assertSee('Wybierz termin i zapłać BLIK-iem, kartą albo przez Przelewy24 — miejsce jest Twoje od razu.');
    }

    public function test_the_footer_names_the_business_from_the_company_details(): void
    {
        $this->get('/sklep')->assertSee('© '.now()->year.' MellowAura</span>', false);

        Setting::query()->where('key', 'company_name')->update(['value' => json_encode('MellowAura Katarzyna Samborska')]);
        Setting::query()->where('key', 'company_nip')->update(['value' => json_encode('1111111111')]);
        app(Settings::class)->refresh();

        $this->get('/sklep')->assertSee('© '.now()->year.' MellowAura Katarzyna Samborska · NIP 1111111111</span>', false);

        Setting::query()->where('key', 'company_name')->update(['value' => json_encode('Pracownia Katarzyna Samborska')]);
        app(Settings::class)->refresh();

        $this->get('/sklep')->assertSee('© '.now()->year.' MellowAura · Pracownia Katarzyna Samborska · NIP 1111111111</span>', false);
    }

    public function test_the_migration_adds_the_starting_values_and_keeps_what_kasia_saved(): void
    {
        Setting::query()->whereIn('key', self::FACTS)->delete();
        Setting::create(['key' => 'text_reply_time', 'value' => 'Odpisuję wieczorem.']);
        Setting::create(['key' => 'text_blik_time', 'value' => null]);

        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_10_30_230000_move_page_facts_into_settings.php');
        $migration->up();

        $this->assertSame('Odpisuję wieczorem.', Setting::find('text_reply_time')->value);
        // Emptied in the panel on purpose: it stays empty.
        $this->assertNull(Setting::find('text_blik_time')->value);
        $this->assertSame('100%', Setting::find('text_home_fact_handmade')->value);
        $this->assertSame("glina\njedwab\nlen\nzłoto 24k", Setting::find('text_home_materials')->value);
        $this->assertSame('Ceramikę zawijam w wióry i podwójny karton', Setting::find('text_checkout_packing')->value);
    }

    private function variant(string $name = 'Wazony'): ProductVariant
    {
        $product = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'category_id' => Category::factory()->create()->id,
            'is_published' => true,
            'stamp_enabled' => false,
        ]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => 23900, 'stock' => 3]);
    }
}
