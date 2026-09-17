<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Enums\FoodContact;
use App\Modules\Catalog\Enums\GoogleCategory;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\PriceHistory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProductsTest extends TestCase
{
    use RefreshDatabase;

    private Category $mugs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->mugs = Category::factory()->create(['name' => 'Kubki i filiżanki', 'slug' => 'kubki-i-filizanki', 'group' => CategoryGroup::Ceramics]);
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/panel/produkty')->assertRedirect('/panel/logowanie');
        $this->post('/panel/produkty')->assertRedirect('/panel/logowanie');
    }

    public function test_the_list_shows_every_product_with_its_state(): void
    {
        $this->product('Wazony', 1, [23900, 29900]);
        $this->product('Szkic miski', 2, [8900], ['is_published' => false]);

        $this->actingAs(User::factory()->create())
            ->get('/panel/produkty')
            ->assertOk()
            ->assertSee('Dodaj produkt')
            ->assertSeeInOrder(['Wszystko, co masz w ofercie', 'Wazony', 'widoczny w sklepie', 'od 239,00 zł', 'Szkic miski', 'ukryty w sklepie', '89,00 zł']);
    }

    public function test_adding_a_product_publishes_it_with_sizes_prices_and_details(): void
    {
        $this->product('Wazony', 1, [23900]);

        $this->actingAs(User::factory()->create())
            ->post('/panel/produkty', [
                'form' => 'nowy-produkt',
                'name' => 'Miska z odciskiem paproci',
                'category_id' => $this->mugs->id,
                'description' => 'Miska z gliny z odciskiem liścia.',
                'care_note' => 'Zmywarka tak',
                'food_contact' => 'not_suitable',
                'deviation' => 'Nie do zmywarki — złota krawędź',
                'size_tolerance' => '1 cm',
                'safety_warnings' => 'Nie stawiaj na ogniu.',
                'is_exact_piece' => '1',
                'google_category' => '3498',
                'show_in_google' => '1',
                'dimensions' => ['diameter_cm' => '12,5', 'height_cm' => ''],
                'occasions' => ['birthday'],
                'recipients' => ['for_her'],
                'is_published' => '1',
                'variants' => [
                    ['label' => 'Mała 12 cm', 'price' => '89', 'stock' => '2'],
                    ['label' => 'Duża 18 cm', 'price' => '129,9', 'stock' => ''],
                    ['label' => '', 'price' => '', 'stock' => ''],
                ],
            ])
            ->assertRedirect('/panel/produkty')
            ->assertSessionHas('panel_status', 'Miska z odciskiem paproci — opublikowane w sklepie');

        $bowl = Product::where('slug', 'miska-z-odciskiem-paproci')->firstOrFail();
        $this->assertSame(['diameter_cm' => '12,5'], $bowl->dimensions);
        $this->assertSame(
            [FoodContact::NotSuitable, 'Nie do zmywarki — złota krawędź', '1 cm', 'Nie stawiaj na ogniu.', true, false],
            [$bowl->food_contact, $bowl->deviation, $bowl->size_tolerance, $bowl->safety_warnings, $bowl->is_exact_piece, $bowl->is_one_off],
        );
        $this->assertSame([['birthday'], ['for_her']], [$bowl->occasions, $bowl->recipients]);
        $this->assertSame([GoogleCategory::Bowls, true], [$bowl->google_category, $bowl->show_in_google]);
        $this->assertTrue($bowl->is_published);
        $this->assertSame(
            [['Mała 12 cm', 8900, 2], ['Duża 18 cm', 12990, null]],
            $bowl->variants()->orderBy('id')->get()->map(fn (ProductVariant $variant) => [$variant->label, $variant->price_gross, $variant->stock])->all(),
        );
        $this->assertSame(3, PriceHistory::count());

        $this->get('/sklep')->assertSeeInOrder(['Miska z odciskiem paproci', 'Wazony']);
    }

    public function test_new_products_go_to_the_top_one_after_another(): void
    {
        $this->product('Wazony', 1, [23900]);
        $owner = User::factory()->create();

        foreach (['Patery', 'Talerze'] as $name) {
            $this->actingAs($owner)
                ->post('/panel/produkty', [
                    'form' => 'nowy-produkt',
                    'name' => $name,
                    'category_id' => $this->mugs->id,
                    'is_published' => '1',
                    'variants' => [['label' => '', 'price' => '99', 'stock' => '1']],
                ])
                ->assertRedirect('/panel/produkty');
        }

        $this->get('/sklep')->assertSeeInOrder(['Talerze', 'Patery', 'Wazony']);
    }

    public function test_editing_records_a_new_price_and_removes_ticked_sizes(): void
    {
        $mug = $this->product('Kubki malowane', 1, [7900, 9900]);
        [$small, $large] = $mug->variants()->orderBy('id')->get()->all();

        $this->actingAs(User::factory()->create())
            ->put('/panel/produkty/'.$mug->id, [
                'form' => 'produkt-'.$mug->id,
                'name' => 'Kubki malowane ręcznie',
                'category_id' => $this->mugs->id,
                'is_published' => '1',
                'variants' => [
                    ['id' => $small->id, 'label' => 'Mały 200 ml', 'price' => '84,50', 'stock' => '1'],
                    ['id' => $large->id, 'label' => 'Duży', 'price' => '99', 'stock' => '', 'remove' => '1'],
                    ['label' => 'Średni 300 ml', 'price' => '89', 'stock' => ''],
                ],
            ])
            ->assertRedirect('/panel/produkty#produkt-'.$mug->id);

        $mug->refresh();
        $this->assertSame(['Kubki malowane ręcznie', 'kubki-malowane'], [$mug->name, $mug->slug]);
        $this->assertSame(
            [['Mały 200 ml', 8450, 1], ['Średni 300 ml', 8900, null]],
            $mug->variants()->orderBy('id')->get()->map(fn (ProductVariant $variant) => [$variant->label, $variant->price_gross, $variant->stock])->all(),
        );
        $this->assertSame([7900, 8450], $small->priceHistory()->orderBy('id')->pluck('price_gross')->all());
        $this->assertNull(ProductVariant::find($large->id));
    }

    public function test_one_price_needs_no_size_name_and_mistakes_come_back_to_their_product(): void
    {
        $mug = $this->product('Kubki malowane', 1, [7900]);
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post('/panel/produkty', [
                'form' => 'nowy-produkt',
                'name' => 'Kadzielnica',
                'category_id' => $this->mugs->id,
                'is_published' => '1',
                'variants' => [['label' => '', 'price' => '59', 'stock' => '1']],
            ])
            ->assertRedirect('/panel/produkty');
        $this->assertSame('', Product::where('slug', 'kadzielnica')->firstOrFail()->variants()->value('label'));

        $this->actingAs($owner)
            ->put('/panel/produkty/'.$mug->id, [
                'form' => 'produkt-'.$mug->id,
                'name' => 'Kubki',
                'category_id' => $this->mugs->id,
                'food_contact' => 'maybe',
                'variants' => [
                    ['label' => '', 'price' => '79', 'stock' => ''],
                    ['label' => 'Duży', 'price' => '79 zł', 'stock' => ''],
                ],
            ])
            ->assertRedirect('/panel/produkty#produkt-'.$mug->id)
            ->assertSessionHasErrorsIn('produkt-'.$mug->id, [
                'variants.0.label' => 'Nazwij każdy rozmiar — np. Mały 12 cm',
                'variants.1.price' => 'Wpisz cenę, np. 79 albo 79,90',
                'food_contact' => 'Wybierz z listy, czy to naczynie do jedzenia',
            ]);

        $this->assertSame('Kubki malowane', $mug->fresh()->name);
    }

    public function test_a_size_can_be_reduced_with_its_price_before_the_reduction(): void
    {
        $vase = $this->product('Wazony', 1, [23900]);
        $size = $vase->variants()->sole();
        $owner = User::factory()->create();
        $form = fn (array $row) => ['form' => 'produkt-'.$vase->id, 'name' => 'Wazony', 'category_id' => $this->mugs->id, 'is_published' => '1', 'variants' => [['id' => $size->id, 'label' => '', 'stock' => '', ...$row]]];

        $this->actingAs($owner)
            ->put('/panel/produkty/'.$vase->id, $form(['price' => '199', 'compare_at' => '199']))
            ->assertSessionHasErrorsIn('produkt-'.$vase->id, ['variants.0.compare_at' => 'Cena przed obniżką musi być wyższa niż obecna — albo zostaw puste pole']);

        $this->put('/panel/produkty/'.$vase->id, $form(['price' => '199', 'compare_at' => '239']))
            ->assertRedirect('/panel/produkty#produkt-'.$vase->id)
            ->assertSessionHas('panel_status', 'Zapisane. Klienci już to widzą.');

        $this->assertSame([19900, 23900], [$size->fresh()->price_gross, $size->fresh()->compare_at_price]);
        $this->get('/produkt/wazony')->assertSeeInOrder(['199,00 zł', '239,00 zł', 'Najniższa cena z 30 dni przed obniżką: 239,00 zł']);
        $this->get('/panel/produkty')->assertSee('value="239"', false);
    }

    public function test_the_form_asks_what_the_terms_need_and_names_the_tolerance_from_the_settings(): void
    {
        Setting::create(['key' => 'size_tolerance', 'value' => '0,5 cm']);
        $this->product('Talerze', 1, [8900], ['food_contact' => FoodContact::Suitable, 'deviation' => 'Nie do mikrofalówki', 'is_exact_piece' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/panel/produkty')
            ->assertOk()
            ->assertSeeInOrder(['Zanim ktoś kupi', 'Kontakt z żywnością', '<option value="suitable" selected>Tak — do jedzenia i picia</option>', 'value="Nie do mikrofalówki"', 'placeholder="jak w Ustawieniach: 0,5 cm"', 'Puste pole — obowiązuje różnica z Ustawień: 0,5 cm.', 'Ostrzeżenia', 'name="is_exact_piece" value="1" checked'], false);
    }

    public function test_a_voucher_size_can_be_sent_by_post_and_goods_are_never_asked(): void
    {
        $vouchers = Category::factory()->create(['name' => 'Vouchery', 'slug' => 'vouchery', 'group' => CategoryGroup::Workshops]);
        $voucher = $this->product('Voucher dla pary', 1, [39000, 39000], ['category_id' => $vouchers->id]);
        [$pdf, $posted] = $voucher->variants()->orderBy('id')->get()->all();
        $this->product('Kubki malowane', 2, [7900]);
        $owner = User::factory()->create();

        $page = $this->actingAs($owner)->get('/panel/produkty')->assertOk()->getContent();
        $this->assertSame(3, substr_count($page, '][sent_by_post]"'), 'Two sizes of the voucher and one spare row, none for the mugs.');
        $this->assertStringContainsString('Zaznacz „pocztą” przy rozmiarze, który drukujesz i wysyłasz', $page);

        $this->actingAs($owner)
            ->put('/panel/produkty/'.$voucher->id, [
                'form' => 'produkt-'.$voucher->id,
                'name' => 'Voucher dla pary',
                'category_id' => $vouchers->id,
                'is_published' => '1',
                'variants' => [
                    ['id' => $pdf->id, 'label' => 'PDF do wydruku', 'price' => '390', 'stock' => ''],
                    ['id' => $posted->id, 'label' => 'Wysyłka pocztą', 'price' => '390', 'stock' => '', 'sent_by_post' => '1'],
                ],
            ])
            ->assertRedirect('/panel/produkty#produkt-'.$voucher->id);

        $this->assertSame([false, true], $voucher->variants()->orderBy('id')->pluck('sent_by_post')->all());
        $this->assertSame([false, true], $voucher->variants()->orderBy('id')->get()->map->needsDelivery()->all());

        // Goods always travel in a parcel, whatever the column says.
        $mug = ProductVariant::query()->whereRelation('product', 'name', 'Kubki malowane')->sole();
        $mug->update(['sent_by_post' => false]);
        $this->assertTrue($mug->needsDelivery());

        $this->actingAs($owner)->get('/panel/produkty')->assertSee('name="variants[1][sent_by_post]" value="1" checked', false);
    }

    public function test_google_shopping_gets_the_kind_from_the_list_and_a_product_can_stay_out_of_it(): void
    {
        $vase = $this->product('Wazony', 1, [23900], ['google_category' => GoogleCategory::Vases]);
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get('/panel/produkty')
            ->assertSeeInOrder(['Zakupy Google', 'Rodzaj w Google', '<option value="">Niech Google dobierze sam</option>', '<option value="602" selected>Wazony</option>', 'name="show_in_google" value="1" checked', 'Pokazuj w Zakupach Google'], false);

        $form = [
            'form' => 'produkt-'.$vase->id,
            'name' => 'Wazony',
            'category_id' => $this->mugs->id,
            'is_published' => '1',
            'variants' => [['id' => $vase->variants()->value('id'), 'label' => '', 'price' => '239', 'stock' => '3']],
        ];

        $this->actingAs($owner)->put('/panel/produkty/'.$vase->id, [...$form, 'google_category' => '999'])
            ->assertSessionHasErrors(['google_category' => 'Wybierz rodzaj z listy albo zostaw „Niech Google dobierze sam”'], errorBag: 'produkt-'.$vase->id);

        $this->actingAs($owner)->put('/panel/produkty/'.$vase->id, [...$form, 'google_category' => ''])->assertRedirect();

        $vase->refresh();
        $this->assertNull($vase->google_category);
        $this->assertFalse($vase->show_in_google);
        $this->assertTrue($vase->is_published);
    }

    public function test_arrows_set_the_order_and_hiding_takes_a_product_off_the_shop(): void
    {
        $vases = $this->product('Wazony', 1, [23900]);
        $this->product('Patery', 2, [32900]);
        $plates = $this->product('Talerze', 3, [8900]);
        $owner = User::factory()->create();

        $this->actingAs($owner)->post('/panel/produkty/'.$plates->id.'/przesun', ['kierunek' => 'wyzej'])->assertRedirect();
        $this->get('/sklep')->assertSeeInOrder(['Wazony', 'Talerze', 'Patery']);

        $this->actingAs($owner)
            ->post('/panel/produkty/'.$vases->id.'/widocznosc')
            ->assertSessionHas('panel_status', 'Wazony — ukryte w sklepie');
        $this->get('/sklep')->assertDontSee('Wazony');
    }

    public function test_the_home_page_hero_is_chosen_here(): void
    {
        $this->product('Wazony', 1, [23900]);
        $this->product('Patery', 2, [32900]);

        $this->actingAs(User::factory()->create())
            ->put('/panel/produkty/strona-glowna', ['home_hero_product' => 'patery', 'home_hero_badge' => 'ostatnie sztuki'])
            ->assertRedirect('/panel/produkty');

        $this->assertSame('patery', Setting::find('home_hero_product')->value);
        $this->get('/')->assertOk()->assertSee('ostatnie sztuki');
    }

    /**
     * @param  list<int>  $prices
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, int $sortOrder, array $prices, array $attributes = []): Product
    {
        $product = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'category_id' => $this->mugs->id,
            'sort_order' => $sortOrder,
            'description' => null,
            'is_published' => true,
            ...$attributes,
        ]);

        foreach ($prices as $price) {
            ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => $price, 'stock' => 2]);
        }

        return $product;
    }
}
