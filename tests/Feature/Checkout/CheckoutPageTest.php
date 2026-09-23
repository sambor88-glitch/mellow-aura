<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Setting::create(['key' => 'free_shipping_threshold', 'value' => 40000]);
        Setting::create(['key' => 'default_payment_method', 'value' => 'blik']);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'note' => '1–2 dni robocze', 'price_gross' => 1600],
            ['code' => 'studio_pickup', 'label' => 'Odbiór w pracowni', 'note' => 'Kraków, po umówieniu', 'price_gross' => 0],
        ]]);
    }

    public function test_the_checkout_shows_contact_payment_delivery_and_the_order_on_one_screen(): void
    {
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900);
        $this->postJson('/koszyk', ['variant_id' => $vase->id]);

        $this->get('/zamowienie')
            ->assertOk()
            ->assertSee('<title>Zamówienie | MellowAura</title>', false)
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSeeInOrder([
                'Jeszcze trzy pola', 'Telefon', 'E-mail', 'Imię i nazwisko', 'Inny adres, faktura na firmę, dopisek do paczki',
                'Płatność', 'BLIK', 'Przelewy24', 'Karta', 'Przelew tradycyjny', 'Przepisz kod z aplikacji banku',
                'Sposób dostawy', 'InPost Paczkomat', '16,00 zł', 'Odbiór w pracowni', 'gratis',
                'Twoje zamówienie', 'Wazony', 'Niski 16 cm', '1 szt.', '239,00 zł', 'Razem', '255,00 zł', 'Płacę 255,00 zł',
            ])
            ->assertSee('name="expected_total" value="25500"', false);
    }

    public function test_the_checkout_is_never_indexed(): void
    {
        config(['app.noindex' => false]);
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900)->id]);

        $this->get('/zamowienie')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_an_empty_cart_leads_back_to_the_shop(): void
    {
        $this->get('/zamowienie')
            ->assertOk()
            ->assertSee('Nic tu jeszcze nie ma')
            ->assertSee('href="'.route('shop.index').'"', false)
            ->assertDontSee('Płacę');
    }

    public function test_the_cart_drawer_leads_to_the_checkout(): void
    {
        $content = $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900)->id])->json('content');

        $this->assertStringContainsString('href="'.route('checkout.index').'"', $content);
        $this->assertStringContainsString('Przejdź do zamówienia', $content);
    }

    private function variant(string $name, string $label, int $price): ProductVariant
    {
        $product = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'category_id' => Category::factory()->create()->id,
            'is_published' => true,
            'stamp_enabled' => false,
        ]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'label' => $label, 'price_gross' => $price, 'stock' => 3]);
    }
}
