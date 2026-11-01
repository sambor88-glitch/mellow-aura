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
        Setting::create(['key' => 'gift_recipients', 'value' => [
            ['key' => 'for_her', 'label' => 'Dla niej'],
            ['key' => 'for_him', 'label' => 'Dla niego / taty'],
            ['key' => 'for_couple', 'label' => 'Dla pary'],
        ]]);
        Setting::create(['key' => 'gift_occasions', 'value' => [
            ['key' => 'birthday', 'label' => 'Urodziny'],
            ['key' => 'mothers_day', 'label' => 'Dzień Matki'],
            ['key' => 'wedding', 'label' => 'Ślub'],
            ['key' => 'housewarming', 'label' => 'Parapetówka'],
            ['key' => 'anniversary', 'label' => 'Rocznica'],
            ['key' => 'christmas', 'label' => 'Święta'],
            ['key' => 'self_gift', 'label' => 'Dla siebie'],
        ]]);
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
            ->assertSeeInOrder(['2 pomysły', 'od 69 zł', 'Kubki malowane ręcznie', 'Wybierz wariant', '239 zł', 'Wazony', 'Do koszyka'])
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
            ->assertSeeInOrder(['Nic nie pasuje do tych warunków', 'Poluzuj budżet albo napisz do mnie', 'href="'.route('custom-orders.index').'"', 'Napisz, czego szukasz'], false)
            ->assertSee('href="'.route('gifts.index').'"', false);
    }

    public function test_filters_with_nothing_to_choose_from_leave_no_empty_box(): void
    {
        $chip = fn (string $label) => ['label' => $label, 'url' => route('gifts.index'), 'active' => true];

        $this->view('gifts::gifts.index', [
            'ideas' => collect(),
            'filters' => ['Dla kogo' => collect([$chip('Dla każdego')]), 'Okazja' => collect([$chip('Wszystkie okazje')]), 'Budżet' => collect()],
            'resetUrl' => route('gifts.index'),
            'filtered' => false,
            'voucherCategory' => null,
            'voucherValidity' => '12 miesięcy',
        ])
            ->assertSee('Powiedz, dla kogo')
            ->assertDontSee('mb-[38px] rounded-[20px] border border-divider bg-cream', false)
            ->assertDontSee('Dla każdego');

        // With a real choice in one filter the box is there with that filter only.
        $this->get('/prezenty')
            ->assertOk()
            ->assertSee('mb-[38px] rounded-[20px] border border-divider bg-cream', false)
            ->assertSee('aria-label="Budżet"', false);
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
            ->assertSee('href="'.route('vouchers.index').'"', false);

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

    public function test_kasia_names_who_a_gift_is_for_and_the_occasions_and_renaming_keeps_the_products_ticked(): void
    {
        $this->product('Kubki malowane ręcznie', [6900], ['birthday'], ['for_her']);
        $this->product('Wazony', [23900], ['wedding'], ['for_her', 'for_couple']);

        $this->actingAs(User::factory()->create())
            ->get('/panel/prezenty')
            ->assertOk()
            ->assertSeeInOrder(['Nagłówek', 'Zdanie pod nagłówkiem', 'Dla kogo', 'Okazja', 'Budżet', 'Pod listą — o voucherach'])
            ->assertSeeInOrder(['name="recipients[0][key]" value="for_her"', 'name="recipients[0][label]" value="Dla niej"', '2 produkty', 'name="recipients[1][label]" value="Dla niego / taty"', '0 produktów', 'name="recipients[2][label]" value="Dla pary"', '1 produkt', 'name="recipients[3][label]" value=""'], false)
            ->assertSee('value="occasions:1:up"', false)
            ->assertSeeInOrder(['name="occasions[0][label]" value="Urodziny"', '1 produkt', 'name="occasions[7][label]" value=""'], false);

        $this->put('/panel/prezenty/szukam-prezentu', [
            'text_gifts_heading' => "Prezent\r\nna każdą okazję ",
            'recipients' => [
                ['key' => 'for_her', 'label' => ' Dla mamy '],
                ['key' => 'for_him', 'label' => 'Dla niego / taty', 'remove' => '1'],
                ['key' => 'for_couple', 'label' => 'Dla pary'],
                ['key' => '', 'label' => 'Dla babci'],
            ],
            'occasions' => [
                ['key' => 'birthday', 'label' => 'Urodziny'],
                ['key' => 'wedding', 'label' => 'Ślub'],
                ['key' => '', 'label' => 'Komunia'],
                ['key' => '', 'label' => ''],
            ],
        ])->assertRedirect('/panel/prezenty#szukam-prezentu')->assertSessionHas('panel_status', 'Zapisane. „Szukam prezentu” już tak wygląda.');

        $this->assertSame(
            [['key' => 'for_her', 'label' => 'Dla mamy'], ['key' => 'for_couple', 'label' => 'Dla pary'], ['key' => 'dla_babci', 'label' => 'Dla babci']],
            Setting::find('gift_recipients')->value,
        );
        $this->assertSame(
            [['key' => 'birthday', 'label' => 'Urodziny'], ['key' => 'wedding', 'label' => 'Ślub'], ['key' => 'komunia', 'label' => 'Komunia']],
            Setting::find('gift_occasions')->value,
        );
        $this->assertSame("Prezent\nna każdą okazję", Setting::find('text_gifts_heading')->value);

        // The renamed tag keeps both products; the removed one is gone from the page and from the address.
        $this->get('/prezenty')
            ->assertSee('Prezent<br>'."\n".'na każdą okazję', false)
            ->assertSeeInOrder(['Dla kogo', 'Dla każdego', 'Dla mamy', 'Dla pary', 'Dla babci', 'Okazja', 'Wszystkie okazje', 'Urodziny', 'Ślub', 'Komunia'])
            ->assertDontSee('Dla niego / taty')
            ->assertDontSee('Parapetówka');
        $this->assertSame(['Dla mamy', 'Wszystkie okazje', 'Każdy budżet'], $this->activeChips('/prezenty?recipient=for_her', '2 pomysły'));
        $this->assertSame(['Dla każdego', 'Wszystkie okazje', 'Każdy budżet'], $this->activeChips('/prezenty?recipient=for_him', '2 pomysły'));
        $this->get('/prezenty?occasion=komunia')->assertSee('Nic nie pasuje do tych warunków');

        // The arrows move one row of the list they belong to and keep what was typed.
        $this->put('/panel/prezenty/szukam-prezentu', [
            'move' => 'occasions:2:up',
            'recipients' => [['key' => 'for_her', 'label' => 'Dla mamy'], ['key' => 'for_couple', 'label' => 'Dla pary'], ['key' => 'dla_babci', 'label' => 'Dla babci']],
            'occasions' => [['key' => 'birthday', 'label' => 'Urodziny'], ['key' => 'wedding', 'label' => 'Ślub'], ['key' => 'komunia', 'label' => 'Komunia (I)']],
        ])->assertSessionHas('panel_status', 'Kolejność zmieniona. Klienci już ją widzą.');

        $this->assertSame(['for_her', 'for_couple', 'dla_babci'], array_column(Setting::find('gift_recipients')->value, 'key'));
        $this->assertSame([['key' => 'birthday', 'label' => 'Urodziny'], ['key' => 'komunia', 'label' => 'Komunia (I)'], ['key' => 'wedding', 'label' => 'Ślub']], Setting::find('gift_occasions')->value);
    }

    public function test_a_tag_list_takes_no_twins_no_long_names_and_a_new_key_never_repeats_one(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put('/panel/prezenty/szukam-prezentu', [
            'occasions' => [['key' => 'wedding', 'label' => 'Ślub'], ['key' => '', 'label' => 'ślub'], ['key' => '', 'label' => str_repeat('a', 41)]],
        ])->assertSessionHasErrorsIn('szukam-prezentu', ['occasions.2.label' => 'Nazwę zmieszczę do 40 znaków']);

        $this->put('/panel/prezenty/szukam-prezentu', [
            'occasions' => [['key' => 'wedding', 'label' => 'Ślub'], ['key' => '', 'label' => 'ślub']],
        ])->assertSessionHasErrorsIn('szukam-prezentu', ['occasions' => '„ślub” jest na liście dwa razy — zmień nazwę albo usuń jedną']);

        $this->put('/panel/prezenty/szukam-prezentu', [
            'occasions' => collect(range(1, 13))->map(fn (int $number) => ['key' => '', 'label' => 'Okazja '.$number])->all(),
        ])->assertSessionHasErrorsIn('szukam-prezentu', ['occasions' => 'Zmieszczę do 12 pozycji']);

        // A new name that makes a key already taken gets the next one, and one of only digits gets a word in front.
        $this->put('/panel/prezenty/szukam-prezentu', [
            'occasions' => [['key' => 'komunia', 'label' => 'Komunia święta'], ['key' => '', 'label' => 'Komunia!'], ['key' => '', 'label' => '18']],
        ])->assertSessionHas('panel_status', 'Zapisane. „Szukam prezentu” już tak wygląda.');

        $this->assertSame(['komunia', 'komunia_2', 'tag_18'], array_column(Setting::find('gift_occasions')->value, 'key'));
        // A list the form does not send stays as it was.
        $this->assertCount(3, Setting::find('gift_recipients')->value);
    }

    public function test_the_product_card_offers_the_tags_from_the_panel_and_takes_no_other(): void
    {
        $vases = $this->product('Wazony', [23900], ['wedding'], ['for_her']);
        Setting::query()->where('key', 'gift_occasions')->update(['value' => json_encode([['key' => 'wedding', 'label' => 'Ślub'], ['key' => 'komunia', 'label' => 'Komunia']])]);
        app(Settings::class)->refresh();

        $this->actingAs(User::factory()->create())
            ->get('/panel/produkty/'.$vases->id.'?tab=gift')
            ->assertOk()
            ->assertSeeInOrder(['Na jaką okazję', 'value="wedding" checked', 'Ślub', 'value="komunia"', 'Komunia', 'Dla kogo', 'value="for_her" checked', 'Dla niej', 'Dla pary'], false)
            ->assertDontSee('Urodziny');

        $this->from('/panel/produkty/'.$vases->id)->put('/panel/produkty/'.$vases->id, [
            'form' => 'produkt-'.$vases->id,
            'name' => 'Wazony',
            'category_id' => $vases->category_id,
            'occasions' => ['birthday'],
            'variants' => [['id' => $vases->variants->first()->id, 'label' => '', 'price' => '239', 'stock' => '']],
        ])->assertSessionHasErrorsIn('produkt-'.$vases->id, ['occasions.0' => 'Wybierz okazję z listy']);

        Setting::query()->where('key', 'gift_recipients')->update(['value' => json_encode([])]);
        app(Settings::class)->refresh();
        $this->get('/panel/produkty/'.$vases->id.'?tab=gift')->assertSee('Lista jest pusta.');
    }

    /**
     * The labels of the chips marked as chosen on a page that counts the given number of ideas.
     *
     * @return list<string>
     */
    private function activeChips(string $url, string $count): array
    {
        $page = $this->get($url)->assertOk()->assertSee($count)->getContent();
        preg_match_all('/aria-current="true"[^>]*>([^<]+)</', $page, $matches);

        return array_map('trim', $matches[1]);
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
