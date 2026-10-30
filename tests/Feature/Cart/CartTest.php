<?php

namespace Tests\Feature\Cart;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_adding_a_product_keeps_the_customer_on_the_page_and_fills_the_drawer(): void
    {
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3);

        $this->from('/produkt/wazony')
            ->post('/koszyk', ['variant_id' => $vase->id, 'quantity' => 2])
            ->assertRedirect('/produkt/wazony');

        $this->get('/produkt/wazony')
            ->assertOk()
            ->assertSeeInOrder(['Koszyk', 'sztuk:', '2'])
            ->assertSeeInOrder(['Twój koszyk', 'Wazony', 'Niski 16 cm', '478 zł', 'Razem', '478 zł']);
    }

    public function test_the_drawer_answers_in_the_background_with_its_new_content(): void
    {
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3);

        $response = $this->postJson('/koszyk', ['variant_id' => $vase->id])
            ->assertOk()
            ->assertJson(['count' => 1, 'notice' => 'Wazony — dodane do koszyka']);

        $this->assertStringContainsString('239 zł', $response->json('content'));
        // The ways to pay under the button are the checkout's own (PaymentMethod::for), not a list typed into the view.
        $this->assertMatchesRegularExpression('/BLIK.*Karta.*Przelewy24.*Przelew tradycyjny/s', $response->json('content'));
    }

    public function test_the_cart_never_holds_more_than_is_on_the_shelf(): void
    {
        $mug = $this->variant('Kubki z cytatem', 'Królowa matka', 7900, stock: 1);

        $this->postJson('/koszyk', ['variant_id' => $mug->id, 'quantity' => 3])
            ->assertJson(['count' => 1, 'notice' => 'To ostatnia sztuka — kolejną zrobię na zamówienie']);

        $this->postJson('/koszyk', ['variant_id' => $mug->id])
            ->assertJson(['count' => 1, 'notice' => 'To ostatnia sztuka — kolejną zrobię na zamówienie']);
    }

    public function test_a_one_off_does_not_promise_another_to_order(): void
    {
        $plate = $this->variant('Talerz z paprocią', 'Jeden', 14900, stock: 1, product: ['is_one_off' => true]);

        $this->postJson('/koszyk', ['variant_id' => $plate->id, 'quantity' => 2])
            ->assertJson(['count' => 1, 'notice' => 'To unikat — drugiej takiej sztuki nie będzie']);
    }

    public function test_changing_the_quantity_and_removing_a_line(): void
    {
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 2);
        $this->postJson('/koszyk', ['variant_id' => $vase->id]);

        $this->patchJson('/koszyk/v'.$vase->id, ['quantity' => 5])
            ->assertJson(['count' => 2, 'notice' => 'Na półce mam 2 szt. — więcej zrobię na zamówienie']);

        // The drawer reads the change out (role="status"), so a screen reader hears more than a new number.
        $this->patchJson('/koszyk/v'.$vase->id, ['quantity' => 1])
            ->assertJson(['count' => 1, 'notice' => 'Wazony — w koszyku: 1 szt.']);

        $response = $this->deleteJson('/koszyk/v'.$vase->id)->assertJson(['count' => 0, 'notice' => 'Wazony — usunięte z koszyka']);

        $this->assertStringContainsString('Jeszcze pusto', $response->json('content'));
    }

    public function test_a_hidden_or_sold_out_product_leaves_the_cart(): void
    {
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 2);
        $plate = $this->variant('Talerze', 'Deserowy', 8900, stock: 2);
        $this->postJson('/koszyk', ['variant_id' => $vase->id]);
        $this->postJson('/koszyk', ['variant_id' => $plate->id]);

        $vase->product->update(['is_published' => false]);
        $plate->update(['stock' => 0]);

        $this->get('/sklep')->assertOk()->assertSee('Jeszcze pusto');
        $this->postJson('/koszyk', ['variant_id' => $vase->id])->assertNotFound();
    }

    public function test_the_product_page_offers_the_chosen_variant_but_not_a_sold_out_one(): void
    {
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3);
        $sold = ProductVariant::factory()->create(['product_id' => $vase->product_id, 'label' => 'Wysoki 24 cm', 'price_gross' => 29900, 'stock' => 0]);

        $this->get('/produkt/wazony')
            ->assertSee('action="'.route('cart.store').'"', false)
            ->assertSee('name="variant_id" value="'.$vase->id.'"', false)
            ->assertSee('Dodaj do koszyka')
            ->assertDontSee('Co mam wbić w glinę?');

        $this->get('/produkt/wazony?wariant='.$sold->id)->assertDontSee('Dodaj do koszyka');
    }

    public function test_the_drawer_counts_down_to_free_shipping(): void
    {
        Setting::create(['key' => 'free_shipping_threshold', 'value' => 40000]);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
            ['code' => 'studio_pickup', 'label' => 'Odbiór w pracowni', 'price_gross' => 0],
        ]]);
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3);

        $content = $this->postJson('/koszyk', ['variant_id' => $vase->id])->json('content');
        $this->assertStringContainsString('Do darmowej wysyłki brakuje 161 zł', $content);
        $this->assertStringContainsString('od 16 zł', $content);

        $content = $this->postJson('/koszyk', ['variant_id' => $vase->id])->json('content');
        $this->assertStringContainsString('Wysyłka gratis — próg osiągnięty', $content);
        $this->assertStringNotContainsString('Do darmowej wysyłki brakuje', $content);
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function variant(string $name, string $label, int $price, ?int $stock, array $product = []): ProductVariant
    {
        $owner = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'category_id' => Category::factory()->create()->id,
            'is_published' => true,
            'stamp_enabled' => false,
            ...$product,
        ]);

        return ProductVariant::factory()->create(['product_id' => $owner->id, 'label' => $label, 'price_gross' => $price, 'stock' => $stock]);
    }
}
