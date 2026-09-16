<?php

namespace Tests\Feature\Gifts;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftFinderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Setting::create(['key' => 'gift_budget_ranges', 'value' => [
            ['min_gross' => 0, 'max_gross' => 10000],
            ['min_gross' => 10000, 'max_gross' => 20000],
            ['min_gross' => 20000, 'max_gross' => 40000],
            ['min_gross' => 40000, 'max_gross' => null],
        ]]);
        Setting::create(['key' => 'text_gifts_lead', 'value' => 'Dobiorę resztę.']);
    }

    public function test_the_page_offers_the_three_filters_and_every_idea_on_sale(): void
    {
        $this->product('Kubki malowane ręcznie', [6900, 7900], ['birthday'], ['for_her', 'for_him']);
        $this->product('Wazony', [23900], ['wedding'], ['for_her']);
        $this->product('Ukryte', [5000], ['birthday'], ['for_her'], published: false);

        $this->get('/prezenty')
            ->assertOk()
            ->assertSee('<title>Szukam prezentu — ceramika handmade na każdą okazję</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('gifts.index').'">', false)
            ->assertSeeInOrder(['href="'.route('gifts.index').'"', 'aria-current="page"', 'Prezenty'], false)
            ->assertSeeInOrder(['szukam prezentu', 'Powiedz, dla kogo', 'i za ile', 'Dobiorę resztę.'])
            ->assertSeeInOrder(['Dla kogo', 'Dla każdego', 'Dla niej', 'Dla niego / taty', 'Dla pary'])
            ->assertSeeInOrder(['Okazja', 'Wszystkie okazje', 'Urodziny', 'Dzień Matki', 'Ślub', 'Parapetówka', 'Rocznica', 'Święta', 'Dla siebie'])
            ->assertSeeInOrder(['Budżet', 'Każdy budżet', 'do 100 zł', '100–200 zł', '200–400 zł', 'powyżej 400 zł'])
            ->assertSeeInOrder(['2 pomysły', 'Kubki malowane ręcznie', 'od 69,00 zł', 'Do koszyka', 'Wazony', '239,00 zł'])
            ->assertDontSee('Ukryte');
    }

    public function test_filters_combine_keep_the_canonical_address_and_link_to_each_other(): void
    {
        $this->product('Kubki malowane ręcznie', [6900, 7900], ['birthday', 'christmas'], ['for_her', 'for_him']);
        $this->product('Kadzielnice', [12900], ['birthday'], ['for_her']);
        $this->product('Patery', [32900], ['birthday'], ['for_him']);
        $this->product('Talerze', [10000], ['birthday'], ['for_him']);

        $this->get('/prezenty?recipient=for_him&occasion=birthday&budget=0-100')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('gifts.index').'">', false)
            ->assertDontSee('noindex')
            ->assertSeeInOrder(['2 pomysły', 'Kubki malowane ręcznie', 'Talerze'])
            ->assertDontSee('Kadzielnice')
            ->assertDontSee('Patery')
            ->assertSee('href="'.route('gifts.index').'?recipient=for_him&amp;occasion=birthday&amp;budget=100-200"', false)
            ->assertSee('href="'.route('gifts.index').'?occasion=birthday&amp;budget=0-100"', false)
            ->assertSeeInOrder(['aria-current="true"', 'Dla niego / taty', 'aria-current="true"', 'Urodziny', 'aria-current="true"', 'do 100 zł'], false);

        // A price on the border belongs to the lower range, and the open range has no top.
        $this->get('/prezenty?budget=100-200')->assertSee('Jeden pomysł')->assertSee('Kadzielnice')->assertDontSee('Talerze');
        $this->get('/prezenty?budget=400-')->assertSee('Nic nie pasuje do tych warunków');
        $this->get('/prezenty?budget=200-400')->assertSee('Patery');

        // Values that are not on the list are ignored.
        $this->get('/prezenty?recipient=for_cat&occasion=&budget=1-2')->assertOk()->assertSee('4 pomysły');
    }

    public function test_an_empty_result_offers_a_way_back_to_every_idea(): void
    {
        $this->product('Wazony', [23900], ['wedding'], ['for_her']);

        $this->get('/prezenty?occasion=anniversary')
            ->assertOk()
            ->assertSeeInOrder(['Nic nie pasuje do tych warunków', 'Poluzuj budżet albo napisz do mnie', 'Pokaż wszystkie pomysły'])
            ->assertSee('href="'.route('gifts.index').'"', false);
    }

    public function test_the_button_adds_the_cheapest_size_on_the_shelf_and_a_mug_with_your_text_goes_to_its_page(): void
    {
        $vases = $this->product('Wazony', [23900, 26900, 29900], ['wedding'], ['for_her']);
        $vases->variants[0]->update(['stock' => 0]);
        $stamped = $this->product('Kubek z Twoim tekstem', [7900], ['birthday'], ['for_her'], product: ['stamp_enabled' => true], stock: null);

        $page = $this->get('/prezenty')->assertOk();

        $page->assertSee('name="variant_id" value="'.$vases->variants[1]->id.'"', false);
        $page->assertSeeInOrder(['Kubek z Twoim tekstem', 'Wybierz napis'])->assertSee('href="'.route('product.show', $stamped).'"', false);
        $this->postJson('/koszyk', ['variant_id' => $vases->variants[1]->id])->assertJson(['count' => 1]);
    }

    public function test_the_voucher_note_shows_with_its_validity_when_vouchers_are_on_sale(): void
    {
        Setting::create(['key' => 'text_gifts_voucher_note', 'value' => 'Voucher kwotowy działa i na warsztaty, i na wszystko ze sklepu.']);
        Setting::create(['key' => 'voucher_validity_months', 'value' => 12]);

        $this->get('/prezenty')->assertDontSee('Nie wiesz, co wybrać?');

        $vouchers = Category::factory()->create(['slug' => 'vouchery', 'name' => 'Vouchery', 'group' => CategoryGroup::Workshops]);
        $this->product('Voucher kwotowy', [15000], ['birthday'], ['for_her'], product: ['category_id' => $vouchers->id], stock: null);

        $this->get('/prezenty')
            ->assertSeeInOrder(['Nie wiesz, co wybrać?', 'Voucher kwotowy działa i na warsztaty, i na wszystko ze sklepu. Ważny rok.', 'Zobacz vouchery'])
            ->assertSee('href="'.route('shop.category', $vouchers).'"', false);

        Setting::query()->where('key', 'voucher_validity_months')->update(['value' => json_encode(6)]);
        app(Settings::class)->refresh();
        $this->get('/prezenty')->assertSee('Ważny 6 miesięcy.');
    }

    public function test_kasia_sets_the_budget_ranges_and_the_sentences_in_the_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/panel/prezenty')
            ->assertOk()
            ->assertSeeInOrder(['Szukam prezentu', 'name="budgets[0][min]" value="0"', 'name="budgets[0][max]" value="100"', 'name="budgets[3][min]" value="400"', 'name="budgets[3][max]" value=""', 'name="budgets[4][min]" value=""'], false);

        $this->put('/panel/prezenty/szukam-prezentu', ['budgets' => [
            ['min' => '150', 'max' => '100'],
            ['min' => '', 'max' => ''],
        ]])->assertSessionHasErrorsIn('szukam-prezentu', ['budgets.0.max' => 'Kwota „do” musi być większa niż „od”']);

        $this->put('/panel/prezenty/szukam-prezentu', [
            'budgets' => [
                ['min' => '300 zł', 'max' => ''],
                ['min' => '0', 'max' => '150'],
                ['min' => '100', 'max' => '200', 'remove' => '1'],
                ['min' => '150', 'max' => '300'],
                ['min' => '', 'max' => ''],
            ],
            'text_gifts_lead' => ' Dobiorę resztę. ',
            'text_gifts_voucher_note' => '',
        ])->assertRedirect('/panel/prezenty#szukam-prezentu');

        $this->assertEquals(
            [['min_gross' => 0, 'max_gross' => 15000], ['min_gross' => 15000, 'max_gross' => 30000], ['min_gross' => 30000, 'max_gross' => null]],
            Setting::find('gift_budget_ranges')->value,
        );
        $this->assertSame('Dobiorę resztę.', Setting::find('text_gifts_lead')->value);
        $this->assertNull(Setting::find('text_gifts_voucher_note')->value);
        $this->get('/prezenty')->assertSeeInOrder(['do 150 zł', '150–300 zł', 'powyżej 300 zł']);
    }

    /**
     * @param  list<int>  $prices
     * @param  list<string>  $occasions
     * @param  list<string>  $recipients
     * @param  array<string, mixed>  $product
     */
    private function product(string $name, array $prices, array $occasions, array $recipients, bool $published = true, array $product = [], ?int $stock = 3): Product
    {
        $owner = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'is_published' => $published,
            'occasions' => $occasions,
            'recipients' => $recipients,
            ...$product,
        ]);

        foreach ($prices as $index => $price) {
            ProductVariant::factory()->create(['product_id' => $owner->id, 'label' => 'Rozmiar '.($index + 1), 'price_gross' => $price, 'stock' => $stock]);
        }

        return $owner->load(['variants' => fn ($query) => $query->orderBy('id')]);
    }
}
