<?php

namespace Tests\Feature\Gifts;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Gifts\Models\Bundle;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BundlesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);
        Setting::create(['key' => 'text_bundles_heading', 'value' => "Glina i jedwab\nw jednym pudełku"]);
        Setting::create(['key' => 'text_bundles_lead', 'value' => 'Dobieram odcień szkliwa do tkaniny.']);
    }

    public function test_the_page_shows_live_sets_with_the_price_and_what_they_save(): void
    {
        $this->bundle('Kubek i scrunchie w jednym kolorze', [['Kubki malowane ręcznie', '300 ml', 7900], ['Scrunchies', 'Średnia', 6900]]);
        $this->bundle('Ukryty zestaw', [['Wazony', 'Niski', 23900], ['Talerze', 'Deserowy', 11000]], published: false);

        $response = $this->get('/zestawy-prezentowe')
            ->assertOk()
            ->assertSee('<title>Zestawy prezentowe — ceramika i jedwab w pudełku</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('bundles.index').'">', false)
            ->assertSeeInOrder(['zestawy prezentowe', "Glina i jedwab\nw jednym pudełku", 'Dobieram odcień szkliwa do tkaniny.'])
            ->assertSeeInOrder(['Kubek i scrunchie w jednym kolorze', 'Kubki malowane ręcznie', 'Scrunchies', '133,00 zł', 'osobno', '148,00 zł', 'taniej o 15,00 zł', 'Do koszyka'])
            ->assertDontSee('Ukryty zestaw');

        $this->assertStringNotContainsString('Zapakuj na prezent', $response->getContent());
    }

    public function test_without_sets_the_page_leads_to_the_shop(): void
    {
        $this->get('/zestawy-prezentowe')
            ->assertOk()
            ->assertSee('Zestawy wrócą, gdy wyjdą z pieca')
            ->assertSee('href="'.route('shop.index').'"', false);
    }

    public function test_a_set_in_the_cart_becomes_one_order_item_per_part_that_adds_up_to_its_price(): void
    {
        Mail::fake();
        $bundle = $this->bundle('Poranek we dwoje', [['Filiżanki', 'Zestaw 2 szt.', 18900], ['Talerze', 'Deserowy 18 cm', 11000]], stock: 2);

        $content = $this->postJson('/koszyk', ['type' => 'bundle', 'bundle_id' => $bundle->id])
            ->assertOk()
            ->assertJson(['count' => 1, 'notice' => 'Poranek we dwoje — zestaw w koszyku'])
            ->json('content');
        $this->assertStringContainsString('Zestaw: Filiżanki (Zestaw 2 szt.) + Talerze (Deserowy 18 cm)', $content);
        $this->assertStringContainsString('269,00 zł', $content);

        $this->patchJson('/koszyk/b'.$bundle->id, ['quantity' => 3])
            ->assertJson(['count' => 2, 'notice' => 'Z tego, co na półce, złożę 2 szt. tego zestawu — więcej zrobię na zamówienie']);

        $this->post('/zamowienie', $this->form(['expected_total' => 2 * 26900 + 1600]))->assertRedirect('/zamowienie/potwierdzenie');

        $items = Order::sole()->items()->orderBy('id')->get();
        $this->assertSame(['Filiżanki', 'Talerze'], $items->pluck('product_name')->all());
        $this->assertSame(['Zestaw 2 szt. · z zestawu „Poranek we dwoje”', 'Deserowy 18 cm · z zestawu „Poranek we dwoje”'], $items->pluck('variant_label')->all());
        $this->assertSame([2, 2], $items->pluck('quantity')->all());
        $this->assertSame(2 * 26900, $items->sum(fn (OrderItem $item) => $item->total()));
        $this->assertSame(55400, Order::sole()->total_gross);
        $this->assertSame([0, 0], $bundle->items->map(fn ($item) => $item->variant->fresh()->stock)->all());
    }

    public function test_a_hidden_set_or_a_sold_out_part_takes_the_set_out_of_the_cart(): void
    {
        $bundle = $this->bundle('Wieczorny rytuał', [['Kadzielnice', 'Pszczoły', 9900], ['Podstawki', 'Mała', 7900]]);
        $this->postJson('/koszyk', ['type' => 'bundle', 'bundle_id' => $bundle->id])->assertJson(['count' => 1]);

        $bundle->items[1]->variant->update(['stock' => 0]);

        $this->get('/zestawy-prezentowe')->assertOk()->assertDontSee('Wieczorny rytuał');
        $this->postJson('/koszyk', ['type' => 'bundle', 'bundle_id' => $bundle->id])->assertNotFound();
        $this->assertStringContainsString('Jeszcze pusto', $this->deleteJson('/koszyk/b999')->json('content'));
    }

    public function test_gift_wrapping_is_one_line_for_the_price_from_the_panel_and_never_a_missing_piece(): void
    {
        Mail::fake();
        Setting::create(['key' => 'gift_wrap_price', 'value' => 1200]);
        Setting::create(['key' => 'text_gift_wrap_heading', 'value' => 'Pakowanie na prezent']);
        $vase = ProductVariant::factory()->for(Product::factory()->state(['name' => 'Wazony']))->create(['price_gross' => 23900, 'stock' => 3]);

        $this->get('/zestawy-prezentowe')->assertOk()->assertSee('Zapakuj na prezent — 12,00 zł');

        $this->postJson('/koszyk', ['variant_id' => $vase->id]);
        $this->postJson('/koszyk', ['type' => 'gift_wrap'])->assertJson(['count' => 2, 'notice' => 'Pakowanie na prezent — dodane do zamówienia']);
        $this->postJson('/koszyk', ['type' => 'gift_wrap'])->assertJson(['count' => 2, 'notice' => 'Pakowanie na prezent jest już w koszyku']);
        $this->get('/zestawy-prezentowe')->assertSee('Pakowanie dodane · usuń');

        $this->post('/zamowienie', $this->form(['expected_total' => 23900 + 1200 + 1600]))->assertRedirect('/zamowienie/potwierdzenie');

        $wrap = Order::sole()->items()->where('product_name', 'Pakowanie na prezent')->sole();
        $this->assertNull($wrap->product_variant_id);
        $this->assertTrue($wrap->is_made_to_order);
        $this->assertSame(0, $wrap->missing_quantity);
        $this->assertFalse(Order::sole()->hasShortage());

        app(MarkOrderPaid::class)(Order::sole(), 'test-repeated-confirmation');
        $this->assertSame(0, $wrap->fresh()->missing_quantity);
    }

    public function test_without_a_price_there_is_no_gift_wrapping(): void
    {
        $this->postJson('/koszyk', ['type' => 'gift_wrap'])->assertNotFound();
        $this->postJson('/koszyk', ['type' => 'nieznany'])->assertNotFound();
    }

    /**
     * @param  list<array{string, string, int}>  $parts
     */
    private function bundle(string $name, array $parts, bool $published = true, int $stock = 3): Bundle
    {
        $bundle = Bundle::factory()->create(['name' => $name, 'discount_percent' => 10, 'is_published' => $published]);

        foreach ($parts as $index => [$product, $label, $price]) {
            $variant = ProductVariant::factory()
                ->for(Product::factory()->state(['name' => $product]))
                ->create(['label' => $label, 'price_gross' => $price, 'stock' => $stock]);
            $bundle->items()->create(['product_variant_id' => $variant->id, 'sort_order' => $index]);
        }

        return $bundle->load('items.variant');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'phone' => '+48 600 100 200',
            'email' => 'ania@example.com',
            'name' => 'Anna Nowak',
            'shipping_method' => 'parcel_locker',
            'payment_method' => 'blik',
            'blik_code' => '123456', 'accept_terms' => '1',
            ...$overrides,
        ];
    }
}
